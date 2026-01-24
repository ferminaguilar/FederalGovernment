(function (Drupal, once) {
  'use strict';

  Drupal.behaviors.aiChatbot = {
    attach: function (context, settings) {
      const toggleChatbot = () => {
        const shouldExpand = document.body.classList.toggle('ai-chatbot-opened');

        if (window.Drupal.ginCoreNavigation) {
          window.Drupal.ginCoreNavigation.collapseToolbar();
        }

        window.localStorage.setItem('Drupal.ai.chatbotExpanded', shouldExpand ? 'true' : 'false');
      };

      once('ai-chatbot', '.button--ai-chatbot', context).forEach(($toolbarIcon) => {
        $toolbarIcon.classList.remove('hidden');

        if ($toolbarIcon.classList.contains('button--primary')) {
          $toolbarIcon.classList.add('button');
        }

        if (window.localStorage.getItem('Drupal.ai.chatbotExpanded') === 'true') {
          toggleChatbot();
        }

        $toolbarIcon.addEventListener('click', (e) => {
          toggleChatbot();
        });
      });

      once('ai-chatbot-toolbar', '.ai-deepchat.toolbar', context).forEach(($chatContainer) => {
        const $dropdownMenu = $chatContainer.querySelector('.chat-dropdown');
        const $menuButton = $chatContainer.querySelector('.chat-dropdown-button');
        const $clearHistoryButton = $chatContainer.querySelector('.clear-history');
        const $closeButton = $chatContainer.querySelector('.toolbar-button.close')

        const toggleMenu = (event) => {
          // Don't run parent event
          event.stopPropagation();
          // Toggle it
          $dropdownMenu.classList.toggle('active');
        }

        $menuButton.addEventListener('click', toggleMenu)
        // Menu items
        $clearHistoryButton.addEventListener('click', (e) => {
          Drupal.clearDeepchatMessages(e);
          toggleMenu(e);
        });

        $closeButton.addEventListener('click', (e) => {
          toggleChatbot(false);
        });
      })
    }
  };

})(Drupal, once);
