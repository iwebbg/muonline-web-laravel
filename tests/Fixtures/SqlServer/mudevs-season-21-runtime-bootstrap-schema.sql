-- Sanitized native columns needed to verify the complete runtime permission profile.
-- Use only inside a fresh disposable SQL Server database. No account data.

CREATE TABLE [dbo].[MEMB_INFO] (
    [memb___id] [varchar](10) NOT NULL,
    [memb__pwd] [varchar](10) NOT NULL,
    [memb_guid] [int] IDENTITY(1,1) NOT NULL,
    [memb_name] [varchar](10) NOT NULL,
    [sno__numb] [char](18) NOT NULL,
    [mail_addr] [varchar](50) NULL,
    [bloc_code] [char](1) NOT NULL,
    [ctl1_code] [char](1) NOT NULL,
    [AccountLevel] [int] NOT NULL,
    [AccountExpireDate] [smalldatetime] NOT NULL
);

CREATE TABLE [dbo].[MEMB_STAT] (
    [memb___id] [varchar](10) NOT NULL,
    [ConnectStat] [tinyint] NULL,
    [ConnectTM] [smalldatetime] NULL,
    [DisConnectTM] [smalldatetime] NULL,
    [OnlineHours] [int] NULL
);

CREATE TABLE [dbo].[AccountCharacter] (
    [Id] [varchar](10) NOT NULL,
    [AccountIndex] [bigint] IDENTITY(1,1) NOT NULL,
    [ExtWarehouse] [int] NOT NULL,
    [GameID1] [varchar](10) NULL,
    [CharacterIndex_1] [bigint] NOT NULL
);

CREATE TABLE [dbo].[Character] (
    [AccountID] [varchar](10) NOT NULL,
    [Name] [varchar](10) NOT NULL,
    [CharacterIndex] [bigint] IDENTITY(1,1) NOT NULL,
    [Class] [tinyint] NULL,
    [cLevel] [int] NULL,
    [LevelUpPoint] [int] NULL,
    [Experience] [int] NULL,
    [Strength] [int] NULL,
    [Dexterity] [int] NULL,
    [Vitality] [int] NULL,
    [Energy] [int] NULL,
    [Leadership] [int] NULL,
    [Inventory] [varbinary](5975) NULL,
    [ExtInventory] [int] NOT NULL,
    [Money] [int] NULL,
    [RuudMoney] [int] NULL,
    [ResetCount] [int] NOT NULL,
    [MasterResetCount] [int] NOT NULL,
    [EnhanceResetCount] [int] NULL,
    [MapNumber] [int] NULL,
    [MapPosX] [smallint] NULL,
    [MapPosY] [smallint] NULL,
    [GiantMountType] [int] NOT NULL,
    [GiantMountStatus] [int] NOT NULL,
    [PkCount] [int] NULL,
    [PkLevel] [int] NULL,
    [PkTime] [int] NULL,
    [CtlCode] [tinyint] NULL,
    [Quest] [varbinary](50) NULL,
    [MagicList] [varbinary](246) NULL
);

CREATE TABLE [dbo].[warehouse] (
    [AccountID] [varchar](10) NOT NULL,
    [AccountIndex] [bigint] NOT NULL,
    [Items] [varbinary](6000) NULL,
    [Money] [int] NULL
);

CREATE TABLE [dbo].[ExtWarehouse] (
    [AccountID] [varchar](10) NOT NULL,
    [AccountIndex] [bigint] NOT NULL,
    [Number] [int] NOT NULL,
    [Items] [varbinary](6000) NULL,
    [Money] [int] NULL
);

CREATE TABLE [dbo].[MuCastle_DATA] (
    [MAP_SVR_GROUP] [int] NOT NULL,
    [SIEGE_START_DATE] [datetime] NOT NULL,
    [SIEGE_END_DATE] [datetime] NOT NULL,
    [SIEGE_ENDED] [bit] NOT NULL,
    [CASTLE_OCCUPY] [bit] NOT NULL,
    [OWNER_GUILD] [varchar](8) NOT NULL
);

CREATE TABLE [dbo].[Guild] (
    [G_Name] [varchar](8) NOT NULL,
    [G_Master] [varchar](10) NULL,
    [G_Mark] [varbinary](32) NULL,
    [G_Score] [int] NULL,
    [G_Count] [int] NULL,
    [G_Notice] [varchar](60) NULL
);

CREATE TABLE [dbo].[GuildMember] (
    [Name] [varchar](10) NOT NULL,
    [G_Name] [varchar](8) NOT NULL
);

CREATE TABLE [dbo].[MasterSkillTree] (
    [CharacterIndex] [bigint] NOT NULL,
    [Name] [varchar](50) NOT NULL,
    [MasterLevel] [int] NULL,
    [MasterPoint] [int] NULL,
    [MasterExperience] [bigint] NULL,
    [MasterSkill] [varbinary](360) NULL
);

CREATE TABLE [dbo].[EnhanceSkillTree] (
    [CharacterIndex] [bigint] NOT NULL,
    [Name] [varchar](50) NOT NULL,
    [EnhancePoint] [int] NULL,
    [EnhanceSkill] [varbinary](480) NULL,
    [EnhanceSkillPassive] [varbinary](160) NULL
);

CREATE TABLE [dbo].[AbilityEnhancementCore] (
    [Account] [varchar](10) NOT NULL,
    [Name] [varchar](10) NOT NULL,
    [CharacterIndex] [bigint] NOT NULL,
    [Inventory] [varbinary](5975) NULL
);

CREATE TABLE [dbo].[CashShopData] (
    [AccountID] [varchar](10) NOT NULL,
    [WCoinC] [int] NULL,
    [WCoinP] [int] NULL,
    [GoblinPoint] [int] NULL
);

CREATE TABLE [dbo].[LuckyCoin] (
    [AccountID] [varchar](10) NOT NULL,
    [LuckyCoin] [int] NULL
);

CREATE TABLE [dbo].[Gens_Rank] (
    [Name] [varchar](10) NOT NULL,
    [CharacterIndex] [bigint] NOT NULL,
    [Family] [int] NOT NULL,
    [Contribution] [int] NULL
);

CREATE TABLE [dbo].[PentagramJewel] (
    [Account] [varchar](10) NOT NULL,
    [Name] [varchar](10) NOT NULL,
    [Type] [tinyint] NOT NULL,
    [Index] [tinyint] NOT NULL,
    [ItemSection] [tinyint] NOT NULL,
    [ItemType] [smallint] NOT NULL
);

CREATE TABLE [dbo].[MuunInventory] (
    [Name] [varchar](10) NOT NULL,
    [CharacterIndex] [bigint] NOT NULL,
    [Items] [varbinary](2575) NULL
);

CREATE TABLE [dbo].[EventInventory] (
    [Name] [varchar](10) NOT NULL,
    [CharacterIndex] [bigint] NOT NULL,
    [Items] [varbinary](800) NULL
);

CREATE TABLE [dbo].[GremoryCase] (
    [Account] [varchar](10) NOT NULL,
    [Name] [varchar](10) NOT NULL,
    [AccountIndex] [bigint] NOT NULL,
    [CharacterIndex] [bigint] NOT NULL,
    [ItemInfo] [varbinary](25) NULL,
    [ExpireDate] [bigint] NULL
);

CREATE TABLE [dbo].[ArtifactSystem] (
    [Name] [varchar](10) NULL,
    [CharacterIndex] [bigint] NOT NULL,
    [GroupIndex] [int] NULL,
    [Slot] [int] NOT NULL,
    [ItemInfo] [varbinary](25) NULL
);

CREATE TABLE [dbo].[WingCoreItem] (
    [coreSerial] [bigint] NOT NULL,
    [lastCharacterIndex] [bigint] NOT NULL,
    [itemInfo] [varbinary](25) NULL
);

CREATE TABLE [dbo].[GameServerInfo] (
    [Number] [int] NOT NULL,
    [ItemCount] [int] NOT NULL,
    [ZenCount] [int] NULL,
    [AceItemCount] [int] NULL
);

