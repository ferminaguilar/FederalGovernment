const uswds = require("@uswds/compile");

/**
 * USWDS version attribute. 3 is for USWDS 3.0+
 */
uswds.settings.version = 3;

/**
 * Path settings
 * Adjusting for a standard Drupal theme structure
 */
uswds.paths.dist.css = './assets/css';
uswds.paths.dist.js = './assets/js';
uswds.paths.dist.theme = './sass'; // Where your settings files will live
uswds.paths.dist.fonts = './assets/fonts';
uswds.paths.dist.img = './assets/img';

exports.init = uswds.init;
exports.compile = uswds.compile;
exports.watch = uswds.watch;
exports.default = uswds.watch;