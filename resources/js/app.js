//
import Alpine from 'alpinejs';
import {
    accountPanel,
    applicationNavigation,
    eventSchedule,
    moderationPanel,
    rankingBoard,
    rankingExplorer,
    resetSettings,
} from './muonline';

window.Alpine = Alpine;

Alpine.data('accountPanel', accountPanel);
Alpine.data('applicationNavigation', applicationNavigation);
Alpine.data('eventSchedule', eventSchedule);
Alpine.data('moderationPanel', moderationPanel);
Alpine.data('rankingBoard', rankingBoard);
Alpine.data('rankingExplorer', rankingExplorer);
Alpine.data('resetSettings', resetSettings);

Alpine.start();
