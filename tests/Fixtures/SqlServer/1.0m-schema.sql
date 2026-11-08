/*
    Sanitized DarksTeam 0.99.60/62T (1.0M) schema contract.

    This fixture intentionally contains no account, character or gameplay data.
    It keeps only the database objects required by the version adapter tests.
*/

CREATE TABLE [dbo].[AccountCharacter] (
    [Number] [int] IDENTITY(1, 1) NOT NULL,
    [Id] [varchar](10) NOT NULL,
    [GameID1] [varchar](10) NULL,
    [GameID2] [varchar](10) NULL,
    [GameID3] [varchar](10) NULL,
    [GameID4] [varchar](10) NULL,
    [GameID5] [varchar](10) NULL,
    [GameIDC] [varchar](10) NULL,
    [MoveCnt] [tinyint] NULL,
    CONSTRAINT [PK_AccountCharacter] PRIMARY KEY NONCLUSTERED ([Id] ASC)
);
GO

CREATE TABLE [dbo].[Character] (
    [AccountID] [varchar](10) NOT NULL,
    [Name] [varchar](10) NOT NULL,
    [cLevel] [int] NULL,
    [LevelUpPoint] [int] NULL,
    [Class] [tinyint] NULL,
    [Experience] [int] NULL,
    [Strength] [smallint] NULL,
    [Dexterity] [smallint] NULL,
    [Vitality] [smallint] NULL,
    [Energy] [smallint] NULL,
    [Inventory] [varbinary](1080) NULL,
    [MagicList] [varbinary](180) NULL,
    [Money] [int] NULL,
    [Life] [real] NULL,
    [MaxLife] [real] NULL,
    [Mana] [real] NULL,
    [MaxMana] [real] NULL,
    [MapNumber] [smallint] NULL,
    [MapPosX] [smallint] NULL,
    [MapPosY] [smallint] NULL,
    [MapDir] [tinyint] NULL,
    [PkCount] [int] NULL,
    [PkLevel] [int] NULL,
    [PkTime] [int] NULL,
    [MDate] [smalldatetime] NULL,
    [LDate] [smalldatetime] NULL,
    [CtlCode] [tinyint] NULL,
    [DbVersion] [tinyint] NULL,
    [Quest] [varbinary](50) NULL,
    [Leadership] [smallint] NULL,
    [ChatLimitTime] [smallint] NULL,
    [Resets] [int] NOT NULL CONSTRAINT [DF_Character_Resets] DEFAULT (0),
    [GrandResets] [int] NOT NULL CONSTRAINT [DF_Character_GrandResets] DEFAULT (0),
    [BanPost] [int] NOT NULL CONSTRAINT [DF_Character_BanPost] DEFAULT (0),
    [IsMarried] [int] NOT NULL CONSTRAINT [DF_Character_IsMarried] DEFAULT (0),
    [MarryName] [varchar](11) NULL,
    CONSTRAINT [PK_Character] PRIMARY KEY NONCLUSTERED ([Name] ASC)
);
GO

CREATE INDEX [IX_Character_AccountID]
    ON [dbo].[Character] ([AccountID] ASC);
GO

CREATE TABLE [dbo].[DefaultClassType] (
    [Class] [tinyint] NOT NULL,
    [Strength] [smallint] NULL,
    [Dexterity] [smallint] NULL,
    [Vitality] [smallint] NULL,
    [Energy] [smallint] NULL,
    [Inventory] [varbinary](1080) NULL,
    [MagicList] [varbinary](180) NULL,
    [Life] [real] NULL,
    [MaxLife] [real] NULL,
    [Mana] [real] NULL,
    [MaxMana] [real] NULL,
    [MapNumber] [smallint] NULL,
    [MapPosX] [smallint] NULL,
    [MapPosY] [smallint] NULL,
    [Quest] [varbinary](50) NULL,
    [DbVersion] [tinyint] NULL,
    [Leadership] [smallint] NULL,
    [Level] [smallint] NULL,
    [LevelUpPoint] [smallint] NULL,
    CONSTRAINT [PK_DefaultClassType] PRIMARY KEY CLUSTERED ([Class] ASC)
);
GO

CREATE TABLE [dbo].[MEMB_INFO] (
    [memb_guid] [int] IDENTITY(1, 1) NOT NULL,
    [memb___id] [varchar](10) NOT NULL,
    [memb__pwd] [varbinary](16) NOT NULL,
    [memb_name] [varchar](10) NOT NULL,
    [sno__numb] [char](13) NOT NULL,
    [post_code] [char](6) NULL,
    [addr_info] [varchar](50) NULL,
    [addr_deta] [varchar](50) NULL,
    [tel__numb] [varchar](20) NULL,
    [phon_numb] [varchar](15) NULL,
    [mail_addr] [varchar](50) NULL,
    [fpas_ques] [varchar](50) NULL,
    [fpas_answ] [varchar](50) NULL,
    [job__code] [char](2) NULL,
    [appl_days] [datetime] NULL,
    [modi_days] [datetime] NULL,
    [out__days] [datetime] NULL,
    [true_days] [datetime] NULL,
    [mail_chek] [char](1) NULL CONSTRAINT [DF_MEMB_INFO_mail_chek] DEFAULT ('0'),
    [bloc_code] [char](1) NOT NULL,
    [ctl1_code] [char](1) NOT NULL,
    [memb__pwd2] [varchar](11) NULL,
    CONSTRAINT [PK_MEMB_INFO] PRIMARY KEY NONCLUSTERED ([memb_guid] DESC),
    CONSTRAINT [UQ_MEMB_INFO_memb___id] UNIQUE CLUSTERED ([memb___id] ASC)
);
GO

CREATE TABLE [dbo].[MEMB_STAT] (
    [memb___id] [varchar](10) NOT NULL,
    [ConnectStat] [tinyint] NULL,
    [ServerName] [varchar](10) NULL,
    [IP] [varchar](15) NULL,
    [ConnectTM] [smalldatetime] NULL,
    [DisConnectTM] [smalldatetime] NULL,
    CONSTRAINT [PK_MEMB_STAT] PRIMARY KEY CLUSTERED ([memb___id] ASC)
);
GO

CREATE TABLE [dbo].[warehouse] (
    [AccountID] [varchar](10) NOT NULL,
    [Items] [varbinary](1200) NULL,
    [Money] [int] NULL CONSTRAINT [DF_warehouse_Money] DEFAULT (0),
    [EndUseDate] [smalldatetime] NULL,
    [DbVersion] [tinyint] NULL CONSTRAINT [DF_warehouse_DbVersion] DEFAULT (0),
    [pw] [smallint] NULL CONSTRAINT [DF_warehouse_pw] DEFAULT (0),
    [MultiVault] [int] NOT NULL CONSTRAINT [DF_warehouse_MultiVault] DEFAULT (1),
    [Items01] [varbinary](1200) NULL,
    [Items02] [varbinary](1200) NULL,
    [Items03] [varbinary](1200) NULL,
    [Items04] [varbinary](1200) NULL,
    [Items05] [varbinary](1200) NULL,
    [Items06] [varbinary](1200) NULL,
    [Items07] [varbinary](1200) NULL,
    [Items08] [varbinary](1200) NULL,
    [Items09] [varbinary](1200) NULL,
    [Items10] [varbinary](1200) NULL,
    CONSTRAINT [PK_warehouse] PRIMARY KEY CLUSTERED ([AccountID] ASC)
);
GO

CREATE TABLE [dbo].[GameServerInfo] (
    [Number] [int] NOT NULL CONSTRAINT [DF_GameServerInfo_Number] DEFAULT (0),
    [ItemCount] [int] NOT NULL,
    [ZenCount] [int] NULL CONSTRAINT [DF_GameServerInfo_ZenCount] DEFAULT (0),
    CONSTRAINT [PK_GameServerInfo] PRIMARY KEY NONCLUSTERED ([Number] ASC)
);
GO

CREATE TABLE [dbo].[MuCastle_DATA] (
    [MAP_SVR_GROUP] [int] NOT NULL,
    [SIEGE_START_DATE] [datetime] NOT NULL,
    [SIEGE_END_DATE] [datetime] NOT NULL,
    [SIEGE_ENDED] [bit] NOT NULL CONSTRAINT [DF_MuCastle_DATA_SIEGE_ENDED] DEFAULT (0),
    [CASTLE_OCCUPY] [bit] NOT NULL CONSTRAINT [DF_MuCastle_DATA_CASTLE_OCCUPY] DEFAULT (0),
    [OWNER_GUILD] [varchar](8) NOT NULL CONSTRAINT [DF_MuCastle_DATA_OWNER_GUILD] DEFAULT (''),
    CONSTRAINT [PK_MuCastle_DATA] PRIMARY KEY CLUSTERED ([MAP_SVR_GROUP] ASC)
);
GO

CREATE TABLE [dbo].[Guild] (
    [G_Name] [varchar](8) NOT NULL,
    [G_Mark] [varbinary](32) NULL,
    [G_Score] [int] NULL CONSTRAINT [DF_Guild_G_Score] DEFAULT (0),
    [G_Master] [varchar](10) NULL,
    [G_Count] [int] NULL,
    [G_Notice] [varchar](60) NULL,
    [Number] [int] IDENTITY(1, 1) NOT NULL,
    [G_Type] [int] NOT NULL CONSTRAINT [DF_Guild_G_Type] DEFAULT (0),
    [G_Rival] [int] NOT NULL CONSTRAINT [DF_Guild_G_Rival] DEFAULT (0),
    [G_Union] [int] NOT NULL CONSTRAINT [DF_Guild_G_Union] DEFAULT (0),
    CONSTRAINT [PK_Guild] PRIMARY KEY CLUSTERED ([G_Name] ASC)
);
GO

CREATE TABLE [dbo].[GuildMember] (
    [Name] [varchar](10) NOT NULL,
    [G_Name] [varchar](8) NOT NULL,
    [G_Level] [tinyint] NULL,
    [G_Status] [tinyint] NOT NULL CONSTRAINT [DF_GuildMember_G_Status] DEFAULT (0),
    CONSTRAINT [PK_GuildMember] PRIMARY KEY CLUSTERED ([Name] ASC),
    CONSTRAINT [FK_GuildMember_Guild] FOREIGN KEY ([G_Name])
        REFERENCES [dbo].[Guild] ([G_Name])
);
GO

CREATE TABLE [dbo].[T_PetItem_Info] (
    [ItemSerial] [int] NOT NULL,
    [Pet_Level] [smallint] NULL CONSTRAINT [DF_T_PetItem_Info_Pet_Level] DEFAULT (0),
    [Pet_Exp] [int] NULL CONSTRAINT [DF_T_PetItem_Info_Pet_Exp] DEFAULT (0),
    CONSTRAINT [PK_T_PetItem_Info] PRIMARY KEY CLUSTERED ([ItemSerial] ASC)
);
GO

CREATE PROCEDURE [dbo].[WZ_GetItemSerial]
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @ItemSerial int;

    BEGIN TRANSACTION;

    UPDATE [dbo].[GameServerInfo]
    SET @ItemSerial = [ItemCount] = [ItemCount] + 1;

    IF @@ERROR <> 0
    BEGIN
        ROLLBACK TRANSACTION;
        SELECT -1;

        RETURN;
    END;

    COMMIT TRANSACTION;
    SELECT @ItemSerial;
END;
GO
