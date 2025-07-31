
/* gulpfile.js */

const uswds = require("@uswds/compile");

/**
 * USWDS version
 * Set the version of USWDS you're using (2 or 3)
 */

uswds.settings.version = 3;

/**
 * Path settings
 * Set as many as you need
 */

uswds.paths.dist.css = './assets/css';
uswds.paths.dist.img = './assets/img';
uswds.paths.dist.fonts = './assets/fonts';
uswds.paths.dist.js = './assets/js';
uswds.paths.dist.sass = './sass';

/**
 * Exports
 * Add as many as you need
 */

exports.init = uswds.init;
exports.compile = uswds.compile;
exports.watch = uswds.watch;
exports.update = uswds.updateUswds;
exports.copyAssets = uswds.copyAssets;
exports.compileSass = uswds.compileSass;
exports.updateUswds = uswds.updateUswds;