//
import Alpine from 'alpinejs';
import {
    accountPanel,
    applicationNavigation,
    contentManager,
    creditPackageManager,
    downloadManager,
    eventSchedule,
    gameServerManager,
    gameMasterManager,
    itemInspector,
    itemGrantManager,
    marketExpiry,
    moderationPanel,
    rankingBoard,
    rankingExplorer,
    registrationRecaptcha,
    serverSwitcher,
    shopOfferEditor,
    walletManager,
    vipPurchaseManager,
} from './muonline';

window.Alpine = Alpine;

Alpine.data('accountPanel', accountPanel);
Alpine.data('applicationNavigation', applicationNavigation);
Alpine.data('contentManager', contentManager);
Alpine.data('creditPackageManager', creditPackageManager);
Alpine.data('downloadManager', downloadManager);
Alpine.data('eventSchedule', eventSchedule);
Alpine.data('gameServerManager', gameServerManager);
Alpine.data('gameMasterManager', gameMasterManager);
Alpine.data('itemInspector', itemInspector);
Alpine.data('itemGrantManager', itemGrantManager);
Alpine.data('marketExpiry', marketExpiry);
Alpine.data('moderationPanel', moderationPanel);
Alpine.data('rankingBoard', rankingBoard);
Alpine.data('rankingExplorer', rankingExplorer);
Alpine.data('registrationRecaptcha', registrationRecaptcha);
Alpine.data('serverSwitcher', serverSwitcher);
Alpine.data('shopOfferEditor', shopOfferEditor);
Alpine.data('walletManager', walletManager);
Alpine.data('vipPurchaseManager', vipPurchaseManager);

Alpine.start();
