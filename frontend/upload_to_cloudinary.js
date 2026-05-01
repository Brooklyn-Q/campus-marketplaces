const path = require('path');
require('dotenv').config({ path: path.resolve(__dirname, '../alwaysdata.env') });
const cloudinary = require('cloudinary').v2;
const fs = require('fs');
const globby = require('globby');

const mediaPattern = 'png|jpe?g|webp|svg|gif|mp4|webm|mp3|wav|ico';
const assetReferencePattern = new RegExp(
  `(?:^|[("'\\\`=\\s])((?:\\/public\\/|\\.\\/)?[A-Za-z0-9_./-]+\\.(?:${mediaPattern})(?:[?#][^"'\\\`\\s)]*)?)`,
  'gi'
);

cloudinary.config({
  cloud_name: process.env.CLOUDINARY_CLOUD_NAME,
  api_key: process.env.CLOUDINARY_API_KEY,
  api_secret: process.env.CLOUDINARY_API_SECRET,
});

function normalizeAssetReference(assetReference) {
  return assetReference
    .split(/[?#]/)[0]
    .replace(/\\/g, '/')
    .replace(/^\/?public\//, '')
    .replace(/^\.\//, '');
}

async function collectReferencedAssets(buildDir) {
  const textFiles = await globby(['index.html', '**/*.css', '**/*.js'], {
    cwd: buildDir,
    onlyFiles: true,
  });
  const assets = new Set();

  for (const relFile of textFiles) {
    const content = fs.readFileSync(path.join(buildDir, relFile), 'utf8');
    for (const match of content.matchAll(assetReferencePattern)) {
      const normalized = normalizeAssetReference(match[1]);
      if (!normalized || normalized.startsWith('assets/dist/')) continue;

      const localAssetPath = path.join(buildDir, normalized);
      if (fs.existsSync(localAssetPath)) {
        assets.add(normalized);
      }
    }
  }

  return Array.from(assets).sort();
}

(async () => {
  const buildDir = path.resolve(__dirname, 'dist');
  const files = await collectReferencedAssets(buildDir);

  if (files.length === 0) {
    console.error('No referenced media files were found in frontend/dist.');
    process.exit(1);
  }

  if (process.env.CLOUDINARY_DRY_RUN === '1') {
    console.log(JSON.stringify(files, null, 2));
    return;
  }

  const urlMap = {};
  console.log(`Uploading ${files.length} referenced media file(s) to Cloudinary...`);

  const MAX_RETRIES = 3;
  const failed = [];

  for (const rel of files) {
    const localPath = path.join(buildDir, rel);
    let uploaded = false;

    for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
      try {
        const resp = await cloudinary.uploader.upload(localPath, {
          folder: 'marketplace_assets',
          public_id: rel.replace(/\.[^.]+$/, ''),
          resource_type: /(?:mp4|webm|mp3|wav)$/i.test(rel) ? 'video' : 'image',
        });
        urlMap[rel] = resp.secure_url;
        console.log(`Uploaded ${rel} -> ${resp.secure_url}`);
        uploaded = true;
        break;
      } catch (err) {
        const msg = err && err.message ? err.message : JSON.stringify(err);
        console.error(`[attempt ${attempt}/${MAX_RETRIES}] Failed to upload ${rel}: ${msg}`);
        if (attempt < MAX_RETRIES) {
          await new Promise(r => setTimeout(r, 2000 * attempt));
        }
      }
    }

    if (!uploaded) {
      failed.push(rel);
      console.error(`Giving up on ${rel} after ${MAX_RETRIES} attempts.`);
    }
  }

  if (failed.length > 0) {
    console.error(`\nERROR: ${failed.length} file(s) failed to upload:\n  ${failed.join('\n  ')}`);
    process.exit(1);
  }

  fs.writeFileSync(path.join(__dirname, 'cloudinary_map.json'), JSON.stringify(urlMap, null, 2));
  console.log('Cloudinary upload map written to cloudinary_map.json');
})().catch(err => {
  console.error('Cloudinary upload failed:', err && err.message ? err.message : err);
  process.exit(1);
});
