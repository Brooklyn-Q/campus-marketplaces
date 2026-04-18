const fs = require('fs');
const path = require('path');

function walk(dir) {
    let results = [];
    const list = fs.readdirSync(dir);
    list.forEach(file => {
        file = path.resolve(dir, file);
        const stat = fs.statSync(file);
        if (stat && stat.isDirectory()) {
            results = results.concat(walk(file));
        } else if (file.endsWith('.tsx') || file.endsWith('.ts')) {
            results.push(file);
        }
    });
    return results;
}

const files = walk('./src');
files.forEach(file => {
    let content = fs.readFileSync(file, 'utf8');
    let modified = false;

    if (content.includes('const assetUrl')) {
        if (!content.includes('path.startsWith(\'uploads/http\')')) {
            content = content.replace(
                /if\s*\(\s*path\.startsWith\('http'\)\s*\)\s*return\s*path;/,
                "if (path.startsWith('uploads/http')) return path.substring(8);\n    if (path.startsWith('http')) return path;"
            );
            modified = true;
        }
    }
    
    // Fix SellerDashboard specifically
    if (content.includes('/marketplace/uploads/') && content.includes('.url')) {
        content = content.replace(
            /\/marketplace\/uploads\/' \+ ([a-zA-Z0-9_.\[\]]+)\.url/g,
            `$1.url.startsWith('http') ? $1.url : '/marketplace/uploads/' + $1.url`
        );
        modified = true;
    }

    if (modified) {
        fs.writeFileSync(file, content);
        console.log('Updated ' + file);
    }
});
