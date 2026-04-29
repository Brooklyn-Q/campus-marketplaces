const fs = require('fs');
const path = require('path');
const globby = require('globby');

const DIST = path.resolve(__dirname, 'dist');
const mapPath = path.join(__dirname, 'cloudinary_map.json');

if (!fs.existsSync(mapPath)) {
  console.error('Cloudinary map not found. Run upload_to_cloudinary.js first.');
  process.exit(1);
}

const urlMap = JSON.parse(fs.readFileSync(mapPath, 'utf8'));

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

function buildReplacementMap() {
  const entries = [];

  for (const [assetPath, url] of Object.entries(urlMap)) {
    const variants = [assetPath, `/public/${assetPath}`, `./${assetPath}`];
    for (const variant of variants) {
      entries.push([variant, url]);
    }
  }

  entries.sort((left, right) => right[0].length - left[0].length);
  return new Map(entries);
}

function replaceInFile(filePath, replacementMap) {
  const content = fs.readFileSync(filePath, 'utf8');
  const pattern = new RegExp(
    Array.from(replacementMap.keys()).map(escapeRegExp).join('|'),
    'g'
  );

  const updatedContent = content.replace(pattern, (match) => {
    return replacementMap.get(match) || match;
  });

  fs.writeFileSync(filePath, updatedContent, 'utf8');
}

(async () => {
  const replacementMap = buildReplacementMap();
  const textFiles = await globby(['**/*.{html,css,js}'], {
    cwd: DIST,
    onlyFiles: true,
  });

  for (const relFile of textFiles) {
    replaceInFile(path.join(DIST, relFile), replacementMap);
  }

  console.log('All local asset URLs replaced with Cloudinary URLs');
})();
