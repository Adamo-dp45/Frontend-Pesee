module.exports = {
    plugins: {
        'postcss-preset-env': {
            stage: 1 // Pour activer des fonctionnalités css modernes
        },
        autoprefixer: {},
        "@tailwindcss/postcss": {}
    },
};