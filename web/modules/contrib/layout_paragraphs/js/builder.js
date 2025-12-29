(($, Drupal, drupalSettings, debounce, Sortable, once) => {
  const idAttr = 'data-lpb-id';

  // The current active component, or NULL.
  let activeComponent = null;

  /**
   * Helper function to select all elements matching a selector.
   *
   * @param {String} selector The selector to query.
   * @param {HTMLElement|Document} element The element to query within.
   * @return {Array} An array of elements matching the selector.
   */
  function selectAll(selector, element = document) {
    return Array.from(element.querySelectorAll(selector));
  }

  /**
   * Helper function to select the first element matching a selector.
   *
   * @param {String} selector The selector to query.
   * @param {HTMLElement|Document} element The element to query within.
   * @return {HTMLElement} The first element matching the selector.
   */
  function select(selector, element = document) {
    return element.querySelector(selector);
  }

  /**
   * Dispatches one or more events on an element.
   *
   * @param {HTMLElement|Window} element The element to dispatch the event on.
   * @param {String|Array} events The event or events to dispatch.
   * @param {Object|null} data The data to pass to the event.
   */
  function dispatch(element, events, data) {
    if (typeof events === 'string') {
      events = [events];
    }
    events.forEach((eventName) => {
      const eventObject = new CustomEvent(`${eventName}`, {
        bubbles: true,
        detail: data,
      });
      element.dispatchEvent(eventObject);
    });
  }

  /**
   * Adds an event listener to an element.
   *
   * @param {HTMLElement|Window} element The element to listen on.
   * @param {Array|String} events The event or events to listen for.
   * @param {Function} callback The callback to run when the event is triggered.
   */
  function listen(element, events, callback) {
    if (typeof events === 'string') {
      events = [events];
    }
    events.forEach((eventName) => {
      element.addEventListener(`${eventName}`, callback);
    });
  }

  /**
   * Sets the visibility of UI elements based on the data-active attribute.
   */
  function setUiElementVisibility() {
    selectAll('.js-lpb-component .js-lpb-ui').forEach((element) => {
      if (element.closest('.js-lpb-component')?.getAttribute('data-active')) {
        element.classList.remove('visually-hidden');
      } else {
        element.classList.add('visually-hidden');
      }
    });
  }

  /**
   * Sets position for the active component's controls.
   */
  const setControlsPosition = () => {
    if (!activeComponent) {
      return;
    }
    const controls = select('.lpb-controls', activeComponent);
    const componentRect = activeComponent.getBoundingClientRect();
    const controlsRect = controls.getBoundingClientRect();
    const scrollY = window.scrollY || document.documentElement.scrollTop;
    const controlsGap = parseInt(
      getComputedStyle(controls).getPropertyValue('--lp-controls-gap'),
      10,
    );
    // If there is not enough space above the component, position the controls below.
    if (scrollY + componentRect.top < controlsRect.height + controlsGap) {
      controls.classList.add('position-below');
      return;
    }
    const thresholdY = parseInt(
      getComputedStyle(controls).getPropertyValue('--lp-controls-sticky-top'),
      10,
    );
    if (componentRect.top - controlsRect.height - controlsGap < thresholdY) {
      if (componentRect.bottom + controlsGap > thresholdY) {
        controls.classList.add('is-fixed');
        controls.classList.remove('position-below');
        activeComponent.style.setProperty(
          '--lp-controls-sticky-left',
          `${componentRect.left + componentRect.width / 2}px`,
        );
      } else {
        controls.classList.remove('is-fixed');
        controls.classList.add('position-below');
      }
    } else {
      controls.classList.remove('is-fixed');
      controls.classList.remove('position-below');
    }
  };

  /**
   * Unselects the currently active component by dispatching a blur event.
   * @param {Event} originalEvent The original event that triggered selection.
   */
  function unselectComponents(originalEvent) {
    dispatch(activeComponent || document, 'lpb-component:blur', {
      componentUuid: activeComponent?.getAttribute('data-uuid') || null,
      layoutId:
        activeComponent?.closest(`[${idAttr}]`)?.getAttribute(idAttr) || null,
      originalEvent,
    });
  }

  /**
   * Handles the blur event for components.
   *
   * Removes focus data attributes from all components.
   */
  function onComponentBlur() {
    selectAll('[data-active="true"]').forEach((element) => {
      element.removeAttribute('data-active');
    });
    selectAll('[data-active-within="true"]').forEach((element) => {
      element.removeAttribute('data-active-within');
    });
    selectAll('.is-navigating').forEach((element) =>
      element.classList.remove('is-navigating'),
    );
    activeComponent = null;
    setUiElementVisibility();
  }

  /**
   * Adds focus data attributes to a component and all its parents.
   *
   * @param {HTMLElement} element The component to focus.
   * @param {Event} originalEvent The original event that triggered selection.
   */
  function selectComponent(element, originalEvent = {}) {
    dispatch(element, 'lpb-component:focus', {
      componentUuid: element.getAttribute('data-uuid'),
      layoutId: element.closest(`[${idAttr}]`).getAttribute(idAttr),
      originalEvent,
    });
  }

  function onComponentFocus(event) {
    const element = event.target;
    if (element === activeComponent) {
      return;
    }
    if (
      element.getAttribute('data-uuid') !==
      activeComponent?.getAttribute('data-uuid')
    ) {
      unselectComponents(event);
    }
    activeComponent = element;
    select('.lpb-hover-label')?.remove();
    // Add the data-active attribute to the element.
    element.setAttribute('data-active', 'true');
    element.setAttribute('data-active-within', 'true');
    // @Todo: Consider a different event name.
    setUiElementVisibility();
    setControlsPosition();
    // Add the data-active-within attribute to all parent elements.
    let parent = element.parentNode.closest('.js-lpb-component');
    while (parent) {
      parent.setAttribute('data-active-within', 'true');
      parent = parent.parentNode.closest('.js-lpb-component');
    }
  }

  /**
   * Adds hover state and tooltip to a component.
   *
   * @param {MouseEvent} event The MouseEvent object triggering the hover.
   */
  function hoverComponent(event) {
    let element = event.target.closest('.js-lpb-component');
    // Remove hover from all components.
    selectAll('[data-hover="true"]').forEach((hoverElement) => {
      hoverElement.removeAttribute('data-hover');
    });
    if (!element) {
      select('.lpb-hover-label')?.remove();
      return;
    }

    // Go to top-most component that does not have the "data-active" attribute.
    while (element) {
      const parentComponent = element.parentNode?.closest('.js-lpb-component');
      if (
        !parentComponent ||
        parentComponent.getAttribute('data-active-within') === 'true'
      ) {
        break;
      }
      element = parentComponent;
    }

    // Add hover to the element.
    if (element && !element.getAttribute('data-active')) {
      element.setAttribute('data-hover', 'true');
      const hoverLabel = select('.lpb-controls-label', element)?.textContent;
      if (hoverLabel) {
        const hoverLabelElement =
          select('.lpb-hover-label') ||
          document.body.appendChild(
            Object.assign(document.createElement('div'), {
              textContent: hoverLabel,
              className: 'lpb-hover-label',
            }),
          );
        hoverLabelElement.textContent = hoverLabel;
        // Set hoverLabelElement position to absolute and mouseX and mouseY.
        hoverLabelElement.style.position = 'absolute';
        hoverLabelElement.style.left = `${event.pageX + 10}px`;
        hoverLabelElement.style.top = `${event.pageY + 10}px`;
      }
    } else {
      select('.lpb-hover-label')?.remove();
    }
  }

  /**
   * Attaches UI elements to $container.
   * @param {jQuery} $container
   *   The container.
   * @param {Object} settings
   *   The settings object.
   */
  function attachUiElements($container, settings) {
    const id = $container.attr('data-lpb-ui-id');
    const lpbBuilderSettings = settings.lpBuilder || {};
    const uiElements = lpbBuilderSettings.uiElements || {};
    const containerUiElements = uiElements[id] || [];
    Object.values(containerUiElements).forEach((uiElement) => {
      const { element, method } = uiElement;
      $container[method]($(element).addClass('js-lpb-ui'));
    });
    setUiElementVisibility();
  }

  /**
   * Repositions open dialogs when their height changes to exceed viewport.
   *
   * The height of an open dialog will change based on its contents and can
   * cause a dialog to grow taller than the current window viewport, making
   * it impossible to reach parts of the content (for example, submit buttons).
   * Repositioning the dialog fixes the issue.
   * @see https://www.drupal.org/project/layout_paragraphs/issues/3252978
   * @see https://stackoverflow.com/questions/5456298/refresh-jquery-ui-dialog-position
   *
   * @param {Number} intervalId
   *   The interval id.
   */
  function repositionDialog(intervalId) {
    const $dialogs = $('.lpb-dialog');
    if ($dialogs.length === 0) {
      clearInterval(intervalId);
      return;
    }
    $dialogs.each((i, dialog) => {
      let bounding = dialog.getBoundingClientRect();
      let viewPortHeight =
        window.innerHeight || document.documentElement.clientHeight;
      if (bounding.bottom > viewPortHeight) {
        const $dialog = $('.ui-dialog-content', dialog);
        if ($dialog.length === 0) {
          return;
        }
        const height = viewPortHeight - 200;
        $dialog.dialog('option', 'height', height);
        $dialog[0].style.overscrollBehavior = 'contain';

        if ($dialog.data('lpOriginalHeight') !== height) {
          $dialog.data('lpOriginalHeight', height);
          bounding = dialog.getBoundingClientRect();
          viewPortHeight =
            window.innerHeight || document.documentElement.clientHeight;
          if (bounding.bottom > viewPortHeight) {
            const pos = $dialog.dialog('option', 'position');
            $dialog.dialog('option', 'position', pos);
          }
        }
      }
    });
  }

  /**
   * Makes an ajax request to reorder all items in the layout.
   * This function is debounced below and not called directly.
   * @param {HTMLElement} element The builder element.
   */
  Drupal.layoutParagraphsUpdateOrder = (element) => {
    const id = element.getAttribute('data-lpb-id');
    const order = Array.from(element.querySelectorAll('.js-lpb-component')).map(
      (item) => {
        return {
          uuid: item.getAttribute('data-uuid'),
          parentUuid:
            item.parentNode
              .closest('.js-lpb-component')
              ?.getAttribute('data-uuid') || null,
          region:
            item.closest('.js-lpb-region')?.getAttribute('data-region') || null,
        };
      },
    );
    Drupal.ajax({
      url: `${drupalSettings.path.baseUrl}${drupalSettings.path.pathPrefix}layout-paragraphs-builder/${id}/reorder`,
      submit: {
        components: JSON.stringify(order),
      },
      error: () => {},
    }).execute();
  };
  const reorderComponents = debounce(Drupal.layoutParagraphsUpdateOrder);

  /**
   * Returns a list of errors for the "accepts" Sortable callback, or an empty array if there are no errors.
   * @param {Element} settings The builder settings.
   * @param {Element} el The element being moved.
   * @param {Element} target The destination
   * @param {Element} source The source
   * @param {Element} sibling The next sibling element
   * @return {Array} An array of errors.
   */
  function acceptsErrors(settings, el, target, source, sibling) {
    return Drupal._lpbMoveErrors.accepts
      .map((validator) =>
        validator.apply(null, [settings, el, target, source, sibling]),
      )
      .filter((errors) => errors !== false && errors !== undefined);
  }

  /**
   * Returns a list of errors for the "moves" Sortable callback, or an empty array if there are no errors.
   * @param {Element} settings The builder settings.
   * @param {Element} el The element being moved.
   * @param {Element} source The source
   * @param {Element} handle The drag handle being grabbed
   * @return {Array} An array of errors.
   */
  function movesErrors(settings, el, source, handle) {
    return Drupal._lpbMoveErrors.moves
      .map((validator) => validator.apply(null, [settings, el, source, handle]))
      .filter((errors) => errors !== false && errors !== undefined);
  }

  /**
   * Moves the focused component up or down the DOM to the next valid position
   * when an arrow key is pressed. Unlike move(), nav() can fully navigate
   * components to any valid position in an entire layout.
   * @param {HTMLElement} element The component to move.
   * @param {int} dir The direction to move (1 == down, -1 == up).
   * @param {boolean} testOnly If true, the move is only tested and not executed.
   * @return {boolean} True if the move was successful, false otherwise.
   */
  function nav(element, dir, testOnly = false) {
    const layoutContainer = element.closest(`[${idAttr}]`);
    const layoutId = layoutContainer.getAttribute(idAttr);
    const settings = drupalSettings.lpBuilder[layoutId];
    layoutContainer.classList.add('is-navigating');

    element.classList.add('lpb-active-item');

    // Add shims as target elements.
    if (dir < 0) {
      Array.from(
        layoutContainer.querySelectorAll(
          '.js-lpb-region .lpb-btn--add.center, .lpb-layout:not(.lpb-active-item)',
        ),
      ).forEach((el) => {
        const shim = document.createElement('div');
        shim.className = 'lpb-shim';
        el.parentNode.insertBefore(shim, el);
      });
    } else if (dir > 0) {
      layoutContainer.querySelectorAll('.js-lpb-region').forEach((region) => {
        const shim = document.createElement('div');
        shim.className = 'lpb-shim';
        region.prepend(shim);
      });
      Array.from(
        layoutContainer.querySelectorAll('.lpb-layout:not(.lpb-active-item)'),
      ).forEach((layout) => {
        const shim = document.createElement('div');
        shim.className = 'lpb-shim';
        layout.parentNode.insertBefore(shim, layout.nextSibling);
      });
    }

    // Build a list of possible targets, or move destinations.
    const targets = Array.from(
      layoutContainer.querySelectorAll('.js-lpb-component, .lpb-shim'),
    )
      .filter((i) => !(element !== i && element.contains(i)))
      .filter((i) => !(i.classList.contains('lpb-layout') && i !== element));

    const currentElement = element;
    let pos = targets.indexOf(currentElement);

    // Check to see if the next position is allowed by calling the 'accepts' callback.
    while (
      targets[pos + dir] !== undefined &&
      acceptsErrors(
        settings,
        element,
        targets[pos + dir].parentNode,
        null,
        element.nextElementSibling || null,
      ).length > 0
    ) {
      pos += dir;
    }

    if (targets[pos + dir] !== undefined) {
      const target = targets[pos + dir];
      if (!testOnly) {
        target.insertAdjacentElement(
          dir === 1 ? 'afterend' : 'beforebegin',
          element,
        );
        dispatch(layoutContainer, 'lpb-component:move', {
          componentUuid: element.getAttribute('data-uuid'),
          layoutId: layoutContainer.getAttribute(idAttr),
        });
      }
    }

    // Remove the shims and save the order.
    Array.from(layoutContainer.querySelectorAll('.lpb-shim')).forEach((shim) =>
      shim.remove(),
    );
    element.classList.remove('lpb-active-item');
    element.focus();

    return targets[pos + dir] !== undefined;
  }

  /**
   * Manages Layout Paragraphs sortable instances.
   *
   * Provides methods to add and retrieve instances by their Layout Paragraphs
   * Layout Ids.
   *
   * @namespace Drupal.LPSortableInstances
   */
  Drupal.LPSortableInstances = {
    /**
     * A map storing instances, keyed by their unique identifiers.
     * Each key maps to a Set of instances.
     *
     * @type {Map<string, Set<any>>}
     */
    instances: new Map(),

    /**
     * Adds a sortable instance associated with a unique identifier.
     * If the identifier does not exist, it initializes a new Set.
     *
     * @param {string} id - The unique identifier for the instance group.
     * @param {*} instance - The instance to be added.
     */
    add(id, instance) {
      if (!this.instances.has(id)) {
        this.instances.set(id, new Set());
      }
      this.instances.get(id).add(instance);
    },

    /**
     * Retrieves all instances associated with a layout instance.
     * Returns an empty Set if no instances are found.
     *
     * @param {string} id - The unique identifier for the instance group.
     * @return {Set<any>} A Set of instances associated with the identifier.
     */
    get(id) {
      return this.instances.get(id) || new Set();
    },
  };

  /**
   * Initializes Sortable.js for drag-and-drop.
   * @param {String} id The layout ID.
   * @param {Array} containers The builder containers.
   * @param {Object} settings The builder settings.
   */
  function initSortable(id, containers, settings) {
    containers.forEach((container) => {
      const layoutContainer = container.closest(`[${idAttr}]`);
      Drupal.LPSortableInstances.add(
        id,
        new Sortable(container, {
          group: `lpb-${id}`,
          draggable: '.js-lpb-component',
          handle: '.lpb-drag',
          ghostClass: 'sortable-ghost',
          animation: 0,
          filter: (event) =>
            movesErrors(settings, null, null, event.target).length > 0,
          onUnchoose: () => {
            Array.from(
              layoutContainer.querySelectorAll(
                '.sortable-to, .sortable-to--disallowed',
              ),
            ).forEach((toElement) => {
              toElement.classList.remove('sortable-to');
              toElement.classList.remove('sortable-to--disallowed');
            });
          },
          onStart: (event) => {
            // Add is-dragging class to the layout container.
            layoutContainer.classList.add('is-dragging');

            // Clone the element and add a class for styling.
            // This is the element that will be left in its original place to
            // retain original dimensions and prevent content reflow while
            // dragging.
            const original = event.item;
            const clone = original.cloneNode(true); // Clone the element
            clone.querySelector('.lpb-drag-image')?.remove();
            clone.classList.add('sortable-placeholder'); // Add a class for styling
            clone.classList.remove('sortable-ghost');
            original.parentNode.insertBefore(clone, original); // Keep the clone in place

            // Dispatch the drag event.
            dispatch(layoutContainer, 'lpb-component:drag', {
              componentUuid: event.item.getAttribute('data-uuid'),
              layoutId: id,
            });
          },
          onEnd: (event) => {
            // Removes the dragging classes.
            layoutContainer.classList.remove('is-dragging');
            event.item.classList.remove('is-dragging');

            // Remove the cloned placeholder element.
            document
              .querySelectorAll('.sortable-placeholder')
              .forEach((placeholderElement) => placeholderElement.remove());

            // Dispatch the drop event.
            dispatch(layoutContainer, 'lpb-component:drop', {
              componentUuid: event.item.getAttribute('data-uuid'),
              layoutId: id,
            });
          },
          onMove: (event) => {
            Array.from(
              layoutContainer.querySelectorAll('.sortable-to'),
            ).forEach((toElement) => {
              toElement.classList.remove('sortable-to');
              toElement.classList.remove('sortable-to--disallowed');
            });
            if (event.to) {
              event.to.classList.add('sortable-to');
            }
            // Prevent dropping directly before the cloned placeholder.
            if (event.related && event.to) {
              const children = Array.from(event.to.children);
              const index = children.indexOf(event.related);
              const nextSibling = event.willInsertAfter
                ? children[index + 1]
                : children[index];
              if (
                nextSibling &&
                nextSibling.classList.contains('sortable-placeholder')
              ) {
                return false;
              }
            }
            // Item cannot be dragged into the destination.
            if (
              acceptsErrors(
                settings,
                event.dragged,
                event.to,
                event.from,
                event.related,
              ).length > 0
            ) {
              if (event.to) {
                event.to.classList.add('sortable-to--disallowed');
              }
              return false;
            }
          },
        }),
      );
    });
  }

  /**
   * Adds a class to regions whose components flow horizontally.
   * @param {Element} regions Region elements to check for horizontal flow.
   */
  function markHorizontalRegions(regions) {
    regions.forEach((region) => {
      const components = Array.from(
        region.querySelectorAll('.js-lpb-component'),
      );
      if (components.length === 0) {
        return;
      }
      const computedStyle = window.getComputedStyle(region);
      const firstComponentStyle = window.getComputedStyle(components[0]);
      // Check if the container is using flexbox
      if (computedStyle.display.includes('flex')) {
        const { flexDirection } = computedStyle; // row, column, etc.
        if (flexDirection.includes('row')) {
          region.classList.add('lpb-horizontal-region');
        }
      }
      // Check if the container is using grid
      else if (computedStyle.display.includes('grid')) {
        const gridTemplateColumns =
          computedStyle.gridTemplateColumns.split(' ').length;
        if (gridTemplateColumns > 1) {
          region.classList.add('lpb-horizontal-region');
        }
      }
      // Check if float is applied to child elements
      else if (firstComponentStyle.float !== 'none') {
        region.classList.add('lpb-horizontal-region');
      }
    });
  }

  // An object with arrays for "accepts" and "moves" Sortable callback functions.
  Drupal._lpbMoveErrors = {
    accepts: [],
    moves: [],
  };
  /**
   * Registers a move validation function.
   * @param {Function} f The validator function.
   * @param {String} c The Sortable callback to register the validator for.
   */
  Drupal.registerLpbMoveError = (f, c = 'accepts') => {
    Drupal._lpbMoveErrors[c].push(f);
  };
  // Checks nesting depth.
  Drupal.registerLpbMoveError((settings, el, target) => {
    if (
      el.hasAttribute('data-layout') &&
      $(target).parents('[data-layout]').length > settings.nesting_depth
    ) {
      return Drupal.t('Exceeds nesting depth of @depth.', {
        '@depth': settings.nesting_depth,
      });
    }
  });
  // If layout is required, prevents component from being placed outside a layout.
  Drupal.registerLpbMoveError((settings, el, target) => {
    if (settings.require_layouts) {
      if (
        el.getAttribute('data-uuid') &&
        !el.hasAttribute('data-layout') &&
        !target.closest('[data-region]')
      ) {
        return Drupal.t('Components must be added inside sections.');
      }
    }
  });
  Drupal.AjaxCommands.prototype.LayoutParagraphsEventCommand = (
    ajax,
    response,
  ) => {
    const { layoutId, componentUuid, eventName, extra } = response;
    const element =
      document.querySelector(`[data-uuid="${componentUuid}"]`) ||
      document.querySelector(`[data-lpb-id="${layoutId}"]`);
    dispatch(element, `lpb-${eventName}`, {
      componentUuid,
      layoutId,
      extra,
    });
  };

  /*
   * Moves the main form action buttons into the jQuery modal button pane.
   * @param {jQuery} context
   *  The context to search for dialog buttons.
   * @return {void}
   */
  function updateDialogButtons(context) {
    // Determine if this context is from within dialog content.
    const $lpDialog = $(context).closest('.ui-dialog-content');

    if (!$lpDialog) {
      return;
    }

    const buttons = [];
    const $buttons = $lpDialog.find(
      '.layout-paragraphs-component-form > .form-actions input[type=submit], .layout-paragraphs-component-form > .form-actions a.button',
    );

    if ($buttons.length === 0) {
      return;
    }

    $buttons.each((_i, el) => {
      const $originalButton = $(el);
      $originalButton[0].style.display = 'none';
      buttons.push({
        text: $originalButton.html() || $originalButton.attr('value'),
        class: $originalButton.attr('class'),
        click(e) {
          // If the original button is an anchor tag, triggering the "click"
          // event will not simulate a click. Use the click method instead.
          if ($originalButton[0].matches('a')) {
            $originalButton[0].click();
          } else {
            $originalButton
              .trigger('mousedown')
              .trigger('mouseup')
              .trigger('click');
            e.preventDefault();
          }
        },
      });
    });

    $lpDialog.dialog('option', 'buttons', buttons);
  }

  Drupal.behaviors.layoutParagraphsBuilder = {
    attach: function attach(context, settings) {
      // Add UI elements to the builder, each component, and each region.
      const jsUiElements = once('lpb-ui-elements', '[data-has-js-ui-element]');
      if (jsUiElements.length) {
        jsUiElements.forEach((el) => {
          attachUiElements($(el), settings);
        });
        // Since we've attached UI elements, we need to reattach behaviors.
        return Drupal.attachBehaviors(context, settings);
      }

      // Initialize Sortable.js for drag-and-drop.
      selectAll('[data-lpb-id]').forEach((layoutElement) => {
        const id = layoutElement.getAttribute('data-lpb-id');
        const containers = once(
          'is-sortable-enabled',
          '.js-lpb-region, .js-lpb-component-list',
          layoutElement,
        );
        initSortable(id, containers, settings.lpBuilder[id]);
      });

      // Add classes to regions whose components flow horizontally.
      //
      // @todo: This should account for responsive layouts.
      //
      markHorizontalRegions(
        once('lpb-horizontal-regions', '.js-lpb-region:has(.js-lpb-component)'),
      );

      // Dispatch lpb-init event on all layout paragraphs builders.
      once('lpb-init', '[data-lpb-id]', context).forEach((el) => {
        dispatch(el, 'lpb-init', {
          layoutId: el.getAttribute('data-lpb-id'),
        });
      });

      // Moves dialog buttons into the jQuery modal button pane.
      updateDialogButtons(context);
    },
  };

  // Move the main form action buttons into the jQuery modal button pane.
  // By default, dialog.ajax.js moves all form action buttons into the button
  // pane -- which can have unintended consequences. We suppress that option
  // by setting drupalAutoButtons to false, but then manually move _only_ the
  // main form action buttons into the jQuery button pane.
  // @see https://www.drupal.org/project/layout_paragraphs/issues/3191418
  // @see https://www.drupal.org/project/layout_paragraphs/issues/3216981

  // Repositions open dialogs.
  // @see https://www.drupal.org/project/layout_paragraphs/issues/3252978
  // @see https://stackoverflow.com/questions/5456298/refresh-jquery-ui-dialog-position

  let lpDialogInterval;

  const handleAfterDialogCreate = (event, dialog, $dialog) => {
    const $element = $dialog || jQuery(event.target);
    if ($element.attr('id').startsWith('lpb-dialog-')) {
      updateDialogButtons($element);
      clearInterval(lpDialogInterval);
      lpDialogInterval = setInterval(
        repositionDialog.bind(null, lpDialogInterval),
        500,
      );
    }
  };
  if (typeof DrupalDialogEvent === 'undefined') {
    $(window).on('dialog:aftercreate', handleAfterDialogCreate);
  } else {
    window.addEventListener('dialog:aftercreate', handleAfterDialogCreate);
  }

  /**
   * Event Listeners
   */

  /**
   * Select the component that was clicked.
   */
  listen(document, 'mouseup', (event) => {
    if (event.target.ownerDocument !== document) {
      return;
    }
    let element = event.target.closest('.js-lpb-component');
    if (element) {
      while (
        element?.parentNode?.closest('.js-lpb-component') &&
        element.parentNode
          .closest('.js-lpb-component')
          ?.getAttribute('data-active-within') !== 'true'
      ) {
        element = element.parentNode.closest('.js-lpb-component');
      }
    }
    if (element) {
      selectComponent(element, event);
    } else {
      unselectComponents(event);
    }
  });

  /**
   * Select a component when focused.
   */
  listen(document, 'focusin', (event) => {
    const component = event.target.closest('.js-lpb-ui')
      ? event.target.closest('.js-lpb-component')
      : null;
    if (component === activeComponent) {
      return;
    }
    if (component) {
      selectComponent(component, event);
    }
  });

  /**
   * Sets a custom drag image for components being dragged for better control of styling.
   */
  listen(document, 'dragstart', (event) => {
    if (event.target.classList.contains('js-lpb-component')) {
      const controls = event.target.querySelector('.lpb-controls');
      const dragImage = controls.cloneNode(false);
      dragImage.classList.add('lpb-drag-image');
      selectAll('.lpb-drag, .lpb-controls-label', controls).forEach((child) => {
        dragImage.appendChild(child.cloneNode(true));
      });
      event.target.prepend(dragImage);
      event.target.classList.add('is-dragging');
      event.dataTransfer.setDragImage(dragImage, 10, 10);
      setTimeout(() => {
        dragImage.remove();
      }, 0);
    }
  });

  /**
   * Handles hover state and tooltip for components.
   */
  listen(document, 'mousemove', hoverComponent);

  /**
   * Selects a newly inserted component.
   */
  listen(document.body, 'lpb-component:insert', (event) => {
    selectComponent(event.target, event);
  });

  /**
   * Selects an updated component if it is still the active one.
   */
  listen(document.body, 'lpb-component:update', (event) => {
    if (
      event.detail.componentUuid === activeComponent?.getAttribute('data-uuid')
    ) {
      selectComponent(event.target, event);
    }
  });

  /**
   * Handles the focus event for components.
   */
  listen(document, 'lpb-component:focus', (event) => {
    onComponentFocus(event);
  });

  /**
   * Handles the blur event for components.
   */
  listen(document, 'lpb-component:blur', (event) => {
    onComponentBlur(event);
  });

  /**
   * Handles keyboard controls and navigation for components.
   */
  listen(document, 'keydown', (event) => {
    if (event.key === 'Escape') {
      return unselectComponents(event);
    }
    if (
      event.key === 'Enter' &&
      event.target.matches('.js-lpb-component:not([data-active="true"])')
    ) {
      return selectComponent(event.target, event);
    }
    if (
      event.key === 'Enter' &&
      event.target.matches('.js-lpb-component[data-active="true"]')
    ) {
      return unselectComponents(event);
    }
    const focused = select('.js-lpb-component[data-active="true"]');
    if (!focused) {
      return;
    }
    switch (event.key) {
      case 'Delete':
      case 'Backspace':
        focused.querySelector('.lpb-delete').click();
        break;
      case 'ArrowUp':
      case 'ArrowLeft':
        nav(focused, -1);
        event.preventDefault();
        event.stopPropagation();
        break;
      case 'ArrowDown':
      case 'ArrowRight':
        nav(focused, 1);
        event.preventDefault();
        event.stopPropagation();
        break;
      default:
        break;
    }
  });

  /**
   * Add is-changed class when a layout is modified.
   */
  listen(
    document.body,
    [
      'lpb-component:insert',
      'lpb-component:update',
      'lpb-component:move',
      'lpb-component:drop',
    ],
    (event) => {
      event.target.closest(`[${idAttr}]`).classList.add('is-changed');
    },
  );

  /**
   * Reorder component on drop or move.
   */
  listen(
    document.body,
    ['lpb-component:drop', 'lpb-component:move'],
    (event) => {
      reorderComponents(event.target.closest(`[${idAttr}]`));
    },
  );

  /**
   * Click handler for various UI elements.
   */
  listen(document, 'click', (event) => {
    // Nothing is "clickable" in components except for .js-lpb-ui elements.
    if (
      !event.target.closest('.js-lpb-ui') &&
      event.target.closest('.js-lpb-component')
    ) {
      event.preventDefault();
      event.stopPropagation();
      return false;
    }
    // Move up / down buttons.
    if (event.target.matches('.lpb-up')) {
      return nav(event.target.closest('.js-lpb-component'), -1);
    }
    if (event.target.matches('.lpb-down')) {
      return nav(event.target.closest('.js-lpb-component'), 1);
    }
    // Removes is-changed class when a form button is clicked.
    if (
      event.target.matches(
        '.form-actions input[type="submit"], .form-actions a',
      )
    ) {
      selectAll('.is-changed').forEach((element) => {
        element.classList.remove('is-changed');
      });
    }
  });

  /**
   * Scroll handler, throttled with requestAnimationFrame().
   * - Removes hover state and tooltip when scrolling.
   * - Sets position for the active component's controls.
   */
  let ticking = false;
  function scrollHandler() {
    document.querySelector('.lpb-hover-label')?.remove();
    setControlsPosition();
  }
  listen(window, ['resize', 'orientationchange', 'scroll'], () => {
    if (!ticking) {
      window.requestAnimationFrame(() => {
        scrollHandler();
        ticking = false;
      });
    }
    ticking = true;
  });

  /**
   * Prevents users from accidentally leaving the page when changes have been made.
   */
  listen(window, 'beforeunload', (event) => {
    if (selectAll('.is-changed').length) {
      event.preventDefault();
    }
  });
})(jQuery, Drupal, drupalSettings, Drupal.debounce, Sortable, once);
