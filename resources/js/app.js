//
import Alpine from 'alpinejs';
import {
    accountPanel,
    applicationNavigation,
    eventSchedule,
    rankingBoard,
    resetSettings,
} from './muonline';

window.Alpine = Alpine;

Alpine.data('accountPanel', accountPanel);
Alpine.data('applicationNavigation', applicationNavigation);
Alpine.data('eventSchedule', eventSchedule);
Alpine.data('rankingBoard', rankingBoard);
Alpine.data('resetSettings', resetSettings);

Alpine.start();
