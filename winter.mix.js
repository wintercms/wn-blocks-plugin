const mix = require('laravel-mix');

mix
    .setPublicPath(__dirname)
    .js('assets/src/js/blocks.js', 'assets/dist/js/blocks.js');
