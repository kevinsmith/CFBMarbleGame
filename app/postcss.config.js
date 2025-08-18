const isProduction = process.env.NODE_ENV === 'production';

module.exports = {
    plugins: {
        "@tailwindcss/postcss": {},
        ...(isProduction && {
            "cssnano": { preset: 'default' }
        }),
        "postcss-hash": {
            manifest: "dist/asset-manifest.json"
        },
    }
}
