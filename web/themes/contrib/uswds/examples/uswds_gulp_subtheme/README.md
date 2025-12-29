# USWDS-gulp Subtheme

## Installation

1. Copy this folder ("uswds_gulp_subtheme") out into the desired location for
  your subtheme (eg. themes/custom/uswds_gulp_subtheme).
1. Rename the folder to the name of your theme (eg. my_renamed_theme).
1. Rename the uswds_gulp_subtheme.info.yml.rename-me file to
  name-of-your-theme.info.yml (eg. my_renamed_theme.info.yml).
1. Tweak that .info.yml file as needed.
1. uswds/compile is already added to package.json, to add it in the theme directory run `npm install`
2. run `gulp init` for Gulp to run the following - [more details can be found here](https://designsystem.digital.gov/documentation/getting-started/developers/phase-two-compile/#step-6-export-compile-functions).
   1. uswds.copyTheme
      5. Copy USWDS theme files (Sass entry point and settings files) from the uswds package to `paths.dist.sass` (defaults to ./sass)
   1. uswds.copyAssets
      1. uswds.copyFonts
         1. Copy USWDS fonts to `paths.dist.fonts` (defautls tp ./assets/uswds/fonts)
      1. uswds.copyImages
         1. Copy USWDS images to `paths.dist.img` (defaults to ./assets/uswds/img)
      1. uswds.copyJS
         1. Copy USWDS compiled JavaScript to `paths.dist.js` (defaults to ./assets/uswds/js)
   6. compile
      1. uswds.compileSass
         1. Compile Sass into `paths.dist.css` (defautls to ./assets/uswds/css)
      1. uswds.compileIcons
         1. Build the USWDS icon sprite into `paths.dist.img` (defautls to ./assets/uswds/img)
1. Enable this theme in the usual way (eg, `drush en my_renamed_subtheme`).
1. Now you can edit the USWDS settings located in the sass directory that was
  added in the root of the theme.
1. Next continue normal theme development.
   1. run `gulp compile` to do a one-time compilation of your SASS ans Icons
   2. run `gulp` to run the default action to watch th SASS files for changes and auto-compile upon file changes.
3. To update your theme when USWDS gets a new release
   1. run `npm update`
      1. downloads the updated USWDS package
   2. run `gulp updateUswds`
      1. uswds.copyAsset and uswds.compile will be run as described in the [USWDS documentation](https://designsystem.digital.gov/documentation/getting-started/developers/phase-two-compile/#step-6-export-compile-functions).
