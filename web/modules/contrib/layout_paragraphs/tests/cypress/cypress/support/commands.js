/**
 * Returns a series of arguments, separated by spaces.
 *
 * @param {*} args An array of arguments.
 * @return {string} The arguments as a string.
 */
function stringifyArguments(args) {
  return args.join(' ');
}

/**
 * Returns a string from an object of options.
 *
 * @param {Object} options An object of options.
 * @return {string} The options as a string.
 */
function stringifyOptions(options) {
  return Object.keys(options)
    .map((option) => {
      let output = `--${option}`;
      if (options[option] === true) {
        return output;
      }
      if (options[option] === false) {
        return '';
      }
      if (typeof options[option] === 'string') {
        output += `="${options[option]}"`;
      } else {
        output += `=${options[option]}`;
      }
      return output;
    })
    .join(' ');
}

/**
 * Run a drush command.
 */
Cypress.Commands.add('drush', (command, args = [], options = {}) => {
  const drushCommand = `${command} ${stringifyArguments(args)} ${stringifyOptions(options)} -y`;
  const execCommand = Cypress.env('drushCommand').replace(
    '$COMMAND',
    drushCommand,
  );
  return cy.exec(execCommand);
});

/**
 * Set field cardinality with proper handling of negative numbers.
 *
 * @param {string} fieldStorage - Field storage config name (e.g., 'node.field_lp_test_content')
 * @param {number} cardinality - Cardinality value (-1 for unlimited, 0 for none, or positive integer)
 */
Cypress.Commands.add('setFieldCardinality', (fieldStorage, cardinality) => {
  // Use php:eval for setting cardinality to avoid argument parsing issues with negative numbers
  // Escape $ signs for shell and use single quotes for the entire PHP string
  const phpCode = `\\$storage = Drupal::entityTypeManager()->getStorage('field_storage_config')->load('${fieldStorage}'); if (\\$storage) { \\$storage->setCardinality(${cardinality}); \\$storage->save(); }`;
  // Execute directly without the drush helper to avoid extra -y being added
  const execCommand = Cypress.env('drushCommand').replace(
    '$COMMAND',
    `php:eval "${phpCode}"`,
  );
  return cy.exec(execCommand);
});

/**
 * Logs out the user.
 */
Cypress.Commands.add('drupalLogout', () => {
  cy.visit('/user/logout');
});

/**
 * Basic user login command. Requires valid username and password.
 *
 * @param {string} username
 *   The username with which to log in.
 * @param {string} password
 *   The password for the user's account.
 */
Cypress.Commands.add('loginAs', (username, password) => {
  cy.session({ username }, () => {
    cy.drupalLogout();
    cy.visit('/user/login');
    cy.get('#edit-name').type(username);
    cy.get('#edit-pass').type(password, {
      log: false,
    });
    cy.get('input#edit-submit').contains('Log in').click();
  });
});

/**
 * Logs a user in by their uid via drush uli.
 */
Cypress.Commands.add('loginUserByUid', (uid) => {
  cy.session({ uid }, () => {
    cy.drush('user-login', [], { uid, uri: Cypress.config('baseUrl') })
      .its('stdout')
      .then((url) => {
        cy.visit(url);
      });
  });
});

/**
 * Logs a user in by their username via drush uli.
 */
Cypress.Commands.add('loginUserByUsername', (username) => {
  cy.session({ username }, () => {
    cy.drush('user-login', [], {
      name: username,
      uri: Cypress.config('baseUrl'),
    })
      .its('stdout')
      .then((url) => {
        cy.visit(url);
      });
  });
});

/**
 * Enables a language on the site if it is not already enabled.
 */
Cypress.Commands.add('enableLanguage', (langCode) => {
  cy.get('#edit-languages').then(($table) => {
    if (
      $table.find(`[data-drupal-selector="edit-languages-${langCode}"]`)
        .length === 0
    ) {
      cy.get('a').contains('Add language').click();
      cy.get('#edit-predefined-langcode').select(langCode);
      cy.get('#edit-predefined-submit').click();
      cy.wait('@addLanguage', { timeout: 70000 });
      cy.url().should('contain', 'admin/config/regional/language');
    }
  });
});

/**
 * Adds a section component to the layout.
 */
Cypress.Commands.add(
  'lpAddSection',
  (layoutChoice = '', selector = '.lpb-btn--add') => {
    cy.get(selector).first().click();
    cy.get('.ui-dialog-title').should('contain', 'Choose a component');
    // Wait for the dialog content to be fully loaded and find any element containing "Section"
    cy.get('.type-lp_test_section').find('a').contains('Section').click();
    cy.get(
      '[data-drupal-selector="edit-layout-paragraphs-component-form-lp-test-section"]',
    ).should('exist');
    cy.get('.ui-dialog-title').should('contain', 'Create new Section');
    cy.get(
      `.layout-select__item input[value="${layoutChoice}"] + label`,
    ).click();
    cy.get('button.lpb-btn--save').click();
    // Replace underscores with hyphens for the CSS class check.
    cy.get(`[data-layout="${layoutChoice}"]`).should('exist');
  },
);

/**
 * Adds a text component to the layout.
 */
Cypress.Commands.add(
  'lpAddTextComponent',
  (text, selector = '.lpb-btn--add') => {
    // The .lpb-btn--add buttons are visually-hidden and can be covered by their
    // parent region div; { force: true } bypasses the coverage check.
    cy.get(selector).first().click({ force: true });
    cy.get('.lpb-component-list a').contains('Text').click();
    cy.lpSetCKEditor5Value('field_lp_test_text', text);
    cy.get('.lpb-btn--save:visible').click();
    // Wait for dialog to close.
    cy.get('.layout-paragraphs-component-form').should('not.exist');
    cy.contains(text).should('be.visible');
  },
);

/**
 * Creates a basic Layout Paragraphs test page.
 */
Cypress.Commands.add('lpCreateTestPage', (title, contentType = 'lp_test_ct') => {
  cy.visit(`/node/add/${contentType}`);
  cy.get('input[name="title[0][value]"]').type(title);
});

/**
 * Saves the current page.
 */
Cypress.Commands.add('lpSavePage', () => {
  // Drupal translation forms use "Save (this translation)" or
  // "Save (all translations)" instead of just "Save".
  cy.get('input[value="Save"], input[value^="Save ("]')
    .filter(':visible')
    .first()
    .click();
});

/**
 * Sets the value of a CKEditor5 Field.
 *
 * @param {string} fieldName
 *   The machine name of the field containing a CKEditor5 widget.
 * @param {string} value
 *   The text or html value to set within the CKEditor5 field widget.
 */
Cypress.Commands.add('lpSetCKEditor5Value', (fieldName, value) => {
  cy.log('Setting CKEditor5 value', { fieldName });

  // Wait for the dialog and form to be ready
  cy.get('.ui-dialog-content', { timeout: 10000 }).should('be.visible');
  cy.get('.layout-paragraphs-component-form').should('exist');
  cy.get('.ck-editor__editable[contenteditable="true"]').should('be.visible');

  const selector = `.field--name-${fieldName.replace(/_/g, '-')}`;
  cy.get(`.layout-paragraphs-component-form ${selector}`).then(($field) => {
    const editor = $field[0].querySelector('.ck-content[contenteditable=true]').ckeditorInstance;
    // Use model.change() to properly trigger change events
    editor.model.change((writer) => {
      // Clear existing content
      const root = editor.model.document.getRoot();
      writer.remove(writer.createRangeIn(root));
      // Insert new content using the data processor
      const viewFragment = editor.data.processor.toView(value);
      const modelFragment = editor.data.toModel(viewFragment);
      writer.insert(modelFragment, root, 0);
    });
  });
});

/**
 * Find a component that contains the given text.
 *
 * @param {string|int} expression
 *   Either The text to search for within the component or the numeric
 *   index of the component to return.
 */
Cypress.Commands.add('lpFindComponent', (expression) => {
  cy.document().then((document) => {
    const component =
      typeof expression === 'number'
        ? Array.from(document.querySelectorAll('[data-uuid]'))[expression - 1]
        : Array.from(document.querySelectorAll('[data-uuid]'))
            .filter((el) => el.textContent.includes(expression))
            .pop();
    cy.lpSelectComponent(component.getAttribute('data-uuid'));
  });
});

/**
 * Selects a component by its UUID, activating it if necessary.
 * This will click the component to activate it, and wait for the edit form to load.
 * If the component is already active, it will simply hover over it.
 * This command will retry clicking the component up to 10 times if it is not active.
 * @param {string} uuid The UUID of the component to select.
 * @throws {Error} If the component cannot be activated after 10 attempts.
 */
Cypress.Commands.add('lpSelectComponent', (uuid) => {
  cy.get(`[data-uuid="${uuid}"]`).then((component) => {
    const clickUntilActive = (i = 0) => {
      if (i > 10) {
        throw new Error(`Failed to activate component with UUID: ${uuid}`);
      }
      cy.get(component).then(($el) => {
        $el[0].dispatchEvent(
          new Event('mouseup', {
            bubbles: true,
            cancelable: true,
          }),
        );
        if ($el.attr('data-active') !== 'true') {
          clickUntilActive(i + 1);
        }
      });
    };
    if (component.attr('data-active') !== 'true') {
      clickUntilActive();
    }
    cy.get(component).trigger('mouseover');
    cy.get(component).as('selectedComponent');
  });
});
