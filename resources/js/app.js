//
import Alpine from 'alpinejs';
import {
    accountPanel,
    applicationNavigation,
    contentManager,
    downloadManager,
    eventSchedule,
    gameMasterManager,
    moderationPanel,
    rankingBoard,
    rankingExplorer,
    resetSettings,
    walletManager,
    vipPurchaseManager,
} from './muonline';

window.Alpine = Alpine;

Alpine.data('accountPanel', accountPanel);
Alpine.data('applicationNavigation', applicationNavigation);
Alpine.data('contentManager', contentManager);
Alpine.data('downloadManager', downloadManager);
Alpine.data('eventSchedule', eventSchedule);
Alpine.data('gameMasterManager', gameMasterManager);
Alpine.data('moderationPanel', moderationPanel);
Alpine.data('rankingBoard', rankingBoard);
Alpine.data('rankingExplorer', rankingExplorer);
Alpine.data('resetSettings', resetSettings);
Alpine.data('walletManager', walletManager);
Alpine.data('vipPurchaseManager', vipPurchaseManager);

Alpine.start();
