//
import Alpine from 'alpinejs';
import {
    accountPanel,
    applicationNavigation,
    contentManager,
    downloadManager,
    eventSchedule,
    gameServerManager,
    gameMasterManager,
    itemInspector,
    marketExpiry,
    moderationPanel,
    rankingBoard,
    rankingExplorer,
    resetSettings,
    serverSwitcher,
    walletManager,
    vipPurchaseManager,
} from './muonline';

window.Alpine = Alpine;

Alpine.data('accountPanel', accountPanel);
Alpine.data('applicationNavigation', applicationNavigation);
Alpine.data('contentManager', contentManager);
Alpine.data('downloadManager', downloadManager);
Alpine.data('eventSchedule', eventSchedule);
Alpine.data('gameServerManager', gameServerManager);
Alpine.data('gameMasterManager', gameMasterManager);
Alpine.data('itemInspector', itemInspector);
Alpine.data('marketExpiry', marketExpiry);
Alpine.data('moderationPanel', moderationPanel);
Alpine.data('rankingBoard', rankingBoard);
Alpine.data('rankingExplorer', rankingExplorer);
Alpine.data('resetSettings', resetSettings);
Alpine.data('serverSwitcher', serverSwitcher);
Alpine.data('walletManager', walletManager);
Alpine.data('vipPurchaseManager', vipPurchaseManager);

Alpine.start();
