/* gulpfile.js */

const uswds = require("@uswds/compile");

/**
 * USWDS version
 * Set the version of USWDS you're using (2 or 3)
 */

uswds.settings.version = 3;

/**
 * Path settings
 * Set as many as you need - see https://designsystem.digital.gov/documentation/getting-started/developers/phase-two-compile/#step-5-customize-path-settings
 */
uswds.paths.dist.css = './assets/uswds/css';
uswds.paths.dist.sass = './sass';
uswds.paths.dist.theme = './sass';
uswds.paths.dist.img = './assets/uswds/img';
uswds.paths.dist.fonts = './assets/uswds/fonts';
uswds.paths.dist.js = './assets/uswds/js';

/**
 * Exports
 * Add as many as you need - see https://designsystem.digital.gov/documentation/getting-started/developers/phase-two-compile/#step-6-export-compile-functions
 */
exports.init = uswds.init;
exports.compile = uswds.compile;
exports.updateUswds = uswds.updateUswds
exports.copyTheme = uswds.copyTheme
exports.watch = uswds.watch
exports.copyAll = uswds.copyAll
exports.copyImages = uswds.copyImages
exports.copyJS = uswds.copyJS
exports.copyAssets = uswds.copyAssets
exports.copyFonts = uswds.copyFonts
exports.default = uswds.default
