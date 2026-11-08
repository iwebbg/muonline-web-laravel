IF COL_LENGTH(N'dbo.Character', N'GrandResets') IS NULL
BEGIN
    ALTER TABLE [dbo].[Character]
    ADD [GrandResets] [int] NOT NULL
        CONSTRAINT [DF_Character_GrandResets] DEFAULT (0) WITH VALUES;
END;
