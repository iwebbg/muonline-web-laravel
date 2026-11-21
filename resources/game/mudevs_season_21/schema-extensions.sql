IF OBJECT_ID(N'dbo.IWEB_S21_ItemOperation', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[IWEB_S21_ItemOperation] (
        [OperationKey] [varchar](128) NOT NULL,
        [Direction] [varchar](32) NOT NULL,
        [AccountID] [varchar](10) NOT NULL,
        [PayloadHash] [char](64) NOT NULL,
        [AppliedAt] [datetime2] NOT NULL
            CONSTRAINT [DF_IWEB_S21_ItemOperation_AppliedAt] DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT [PK_IWEB_S21_ItemOperation] PRIMARY KEY ([OperationKey], [Direction])
    );
END;

IF OBJECT_ID(N'dbo.IWEB_S21_CharacterOperation', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[IWEB_S21_CharacterOperation] (
        [OperationKey] [varchar](128) NOT NULL,
        [AccountID] [varchar](10) NOT NULL,
        [PayloadHash] [char](64) NOT NULL,
        [ResultJson] [nvarchar](max) NOT NULL,
        [AppliedAt] [datetime2] NOT NULL
            CONSTRAINT [DF_IWEB_S21_CharacterOperation_AppliedAt] DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT [PK_IWEB_S21_CharacterOperation] PRIMARY KEY ([OperationKey])
    );
END;

IF OBJECT_ID(N'dbo.IWEB_S21_AccountPurchase', N'U') IS NULL
BEGIN
    CREATE TABLE [dbo].[IWEB_S21_AccountPurchase] (
        [OperationKey] [varchar](128) NOT NULL,
        [AccountID] [varchar](10) NOT NULL,
        [PayloadHash] [char](64) NOT NULL,
        [ResultJson] [nvarchar](max) NOT NULL,
        [AppliedAt] [datetime2] NOT NULL
            CONSTRAINT [DF_IWEB_S21_AccountPurchase_AppliedAt] DEFAULT (SYSUTCDATETIME()),
        CONSTRAINT [PK_IWEB_S21_AccountPurchase] PRIMARY KEY ([OperationKey])
    );
END;
