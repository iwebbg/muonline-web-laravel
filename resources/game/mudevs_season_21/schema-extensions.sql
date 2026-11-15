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
