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
