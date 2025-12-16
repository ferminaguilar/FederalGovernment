/* gulpfile.js */

// eslint-disable-next-line import/no-unresolved
const uswds = require('@uswds/compile');

/**
 * USWDS version
 */

uswds.settings.version = 3;

/**
 * Path settings
 * Set as many as you need
 */

uswds.paths.dist.css = 'web/themes/contrib/fedgov/assets/css';
uswds.paths.dist.js = 'web/themes/contrib/fedgov/assets/js';
uswds.paths.dist.theme = 'web/themes/contrib/fedgov/assets/sass';

/**
 * Exports
 * Add as many as you need
 */

exports.init = uswds.init;
exports.compile = uswds.compile;
