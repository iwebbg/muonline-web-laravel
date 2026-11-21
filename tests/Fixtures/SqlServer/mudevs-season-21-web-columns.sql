-- Portable native S21 columns used by guarded website services.
-- Apply after mudevs-season-21-read-schema.sql in disposable databases only.
ALTER TABLE Character ADD Experience INT NULL;
ALTER TABLE Character ADD MapPosX SMALLINT NULL;
ALTER TABLE Character ADD MapPosY SMALLINT NULL;
ALTER TABLE Character ADD PkTime INT NULL;
ALTER TABLE Character ADD CtlCode SMALLINT NULL;
ALTER TABLE Character ADD RuudMoney INT NULL;
ALTER TABLE Character ADD EnhanceResetCount INT NULL;
ALTER TABLE Character ADD Inventory VARBINARY(5975) NULL;
ALTER TABLE Character ADD Quest VARBINARY(50) NULL;
ALTER TABLE Character ADD MagicList VARBINARY(246) NULL;
ALTER TABLE Character ADD GiantMountType INT NULL;
ALTER TABLE Character ADD GiantMountStatus INT NULL;
ALTER TABLE Character ADD ExtInventory INT NULL;
ALTER TABLE Character ADD LastGateIndex INT NULL;

CREATE TABLE AccountCharacter (
    AccountIndex BIGINT NOT NULL PRIMARY KEY,
    Id VARCHAR(10) NOT NULL UNIQUE,
    ExtWarehouse INT NOT NULL,
    GameID1 VARCHAR(10) NULL,
    GameID2 VARCHAR(10) NULL,
    GameID3 VARCHAR(10) NULL,
    GameID4 VARCHAR(10) NULL,
    GameID5 VARCHAR(10) NULL,
    GameID6 VARCHAR(10) NULL,
    GameID7 VARCHAR(10) NULL,
    GameID8 VARCHAR(10) NULL,
    GameID9 VARCHAR(10) NULL,
    GameID10 VARCHAR(10) NULL,
    GameIDC VARCHAR(10) NULL,
    CharacterIndex_1 BIGINT NULL,
    CharacterIndex_2 BIGINT NULL,
    CharacterIndex_3 BIGINT NULL,
    CharacterIndex_4 BIGINT NULL,
    CharacterIndex_5 BIGINT NULL,
    CharacterIndex_6 BIGINT NULL,
    CharacterIndex_7 BIGINT NULL,
    CharacterIndex_8 BIGINT NULL,
    CharacterIndex_9 BIGINT NULL,
    CharacterIndex_10 BIGINT NULL
);

CREATE TABLE CashShopData (
    AccountID VARCHAR(10) NOT NULL PRIMARY KEY,
    WCoinC INT NULL,
    WCoinP INT NULL,
    GoblinPoint INT NULL
);

CREATE TABLE LuckyCoin (
    AccountID VARCHAR(10) NOT NULL PRIMARY KEY,
    LuckyCoin INT NULL
);

-- Application-owned native custody receipts; not part of the emulator source schema.
CREATE TABLE IWEB_S21_ItemOperation (
    OperationKey VARCHAR(128) NOT NULL,
    Direction VARCHAR(32) NOT NULL,
    AccountID VARCHAR(10) NOT NULL,
    PayloadHash CHAR(64) NOT NULL,
    AppliedAt DATETIME2 NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (OperationKey, Direction)
);

CREATE TABLE IWEB_S21_CharacterOperation (
    OperationKey VARCHAR(128) NOT NULL PRIMARY KEY,
    AccountID VARCHAR(10) NOT NULL,
    PayloadHash CHAR(64) NOT NULL,
    ResultJson TEXT NOT NULL,
    AppliedAt DATETIME2 NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IWEB_S21_AccountPurchase (
    OperationKey VARCHAR(128) NOT NULL PRIMARY KEY,
    AccountID VARCHAR(10) NOT NULL,
    PayloadHash CHAR(64) NOT NULL,
    ResultJson TEXT NOT NULL,
    AppliedAt DATETIME2 NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- Reviewed mutable identity projection; binary contents and historical records remain untouched.
CREATE TABLE [AbilityEnhancementCore] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Inventory] VARBINARY(5975) NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [ARCA_BATTLE_GUILD_JOIN_INFO] (
    [G_Master] VARCHAR(10) NULL
);

CREATE TABLE [ARCA_BATTLE_GUILDMARK_REG] (
    [G_Master] VARCHAR(10) NULL
);

CREATE TABLE [ARCA_BATTLE_MEMBER_JOIN_INFO] (
    [CharName] VARCHAR(10) NULL
);

CREATE TABLE [ARCA_BATTLE_PERIOD_BUFF_INFO] (
    [CharacterName] VARCHAR(10) NULL
);

CREATE TABLE [ArtifactSystem] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [BanMacInfo] (
    [Account] VARCHAR(11) NULL,
    [Name] VARCHAR(11) NULL
);

CREATE TABLE [BattleCoreUser] (
    [AccountID] VARCHAR(10) NULL,
    [Name] VARCHAR(10) NULL,
    [ServerCode] SMALLINT NULL
);

CREATE TABLE [BattlePassDailyMissionInfo] (
    [Account] NCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] NCHAR(10) NULL
);

CREATE TABLE [BattlePassInfo] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [BlockChat] (
    [BlockName] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [ChaosCastleFinal_Ranking] (
    [mName] VARCHAR(10) NULL
);

CREATE TABLE [ChaosCastleFinal_Scores] (
    [mName] VARCHAR(10) NULL
);

ALTER TABLE [EnhanceSkillTree] ADD [EnhanceSkill] VARBINARY(480) NULL;

ALTER TABLE [EnhanceSkillTree] ADD [EnhanceSkillPassive] VARBINARY(160) NULL;

CREATE TABLE [EventEntryCount] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [EventEntryLimit] (
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [EventInventory] (
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [EventLeoTheHelper] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [EventSantaClaus] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [FavoriteWarpList] (
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [Gens_Left] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [Gens_Reward] (
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [GremoryCase] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [HelperData] (
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [IllusionTempleLeague_UserPoint] (
    [mUserName] VARCHAR(10) NULL
);

CREATE TABLE [IllusionTempleLeague_UserReward] (
    [mUserName] VARCHAR(10) NULL
);

CREATE TABLE [ItemCompensationLevel] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

ALTER TABLE [MasterSkillTree] ADD [MasterSkill] VARBINARY(360) NULL;

CREATE TABLE [MonsterSoulConverterItem] (
    [Account] NCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] NCHAR(10) NULL
);

CREATE TABLE [MonsterSoulConverterWeeklyPurchases] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [MuHelperPlus] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [MuQuestInfo] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [MuRummyCard] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [MuRummyData] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [MuunInventory] (
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [OptionData] (
    [ChangeSkin] TINYINT NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [PartyRenewaInfo] (
    [LeaderName] VARCHAR(10) NULL
);

CREATE TABLE [PartyRenewalMemberList] (
    [Class] INT NULL,
    [LeaderName] VARCHAR(10) NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [PentagramJewel] (
    [Account] VARCHAR(10) NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [PersonalShopRenewalList] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [PlayTimeEvent] (
    [CharacterIndex] BIGINT NULL,
    [account] NCHAR(10) NULL,
    [name] NCHAR(10) NULL
);

CREATE TABLE [PShopItemValue] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [QuestGuide] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [QuestKillCount] (
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [QuestWorld] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [RankingBloodCastle] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [RankingCastleSiege] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [RankingChaosCastle] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [RankingDevilSquare] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [RankingDuel] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [RankingIllusionTemple] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [RestoreOffline] (
    [Account] VARCHAR(10) NULL,
    [Name] VARCHAR(10) NULL,
    [ServerCode] INT NULL,
    [Status] INT NULL
);

CREATE TABLE [SNSData] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [T_ARCA_BATTLE_GUILD_JOIN_INFO] (
    [G_Master] VARCHAR(10) NULL
);

CREATE TABLE [T_ARCA_BATTLE_GUILDMARK_REG] (
    [G_Master] VARCHAR(10) NULL
);

CREATE TABLE [T_ARCA_BATTLE_MEMBER_JOIN_INFO] (
    [CharName] VARCHAR(10) NULL
);

CREATE TABLE [T_BombHunt] (
    [AccountID] VARCHAR(10) NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [T_CGuid] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [T_EvolutionMonster] (
    [AccountID] VARCHAR(10) NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [T_FriendList] (
    [FriendName] VARCHAR(10) NULL
);

CREATE TABLE [T_FriendMail] (
    [FriendName] VARCHAR(10) NULL
);

CREATE TABLE [T_FriendMain] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [T_HuntingRecordOption] (
    [AccountID] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [T_LabyrinthLeague] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [T_LabyrinthLeagueReward] (
    [Account] VARCHAR(10) NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [T_PartyMatching] (
    [LeaderClass] TINYINT NULL,
    [PartyLeaderName] VARCHAR(10) NULL
);

CREATE TABLE [T_PartyMatchingRecruitInfo] (
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [T_PeriodBuffInfo] (
    [CharacterName] CHAR(10) NULL
);

CREATE TABLE [T_QUEST_EXP_INFO] (
    [CHAR_NAME] VARCHAR(10) NULL
);

CREATE TABLE [T_RestoreItem_Inventory] (
    [AccountID] VARCHAR(10) NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [T_WaitFriend] (
    [FriendName] VARCHAR(10) NULL
);

CREATE TABLE [T_WaitPartyMatching] (
    [Class] TINYINT NULL,
    [LeaderName] VARCHAR(10) NULL,
    [MemberName] VARCHAR(10) NULL
);

CREATE TABLE [WingExchangeItem] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);

CREATE TABLE [DeathExpRecovery] (
    [Account] VARCHAR(10) NULL,
    [CharacterIndex] BIGINT NULL,
    [Name] VARCHAR(10) NULL
);
