-- Sanitized read-model projection of the supplied MUDevs S21.1-2 schema.
-- Load only in disposable test databases. No accounts or server procedures.
-- Explicit identities let the same read fixtures run on SQLite and SQL Server.

CREATE TABLE warehouse (
    AccountID VARCHAR(10) NOT NULL PRIMARY KEY,
    AccountIndex INT NOT NULL,
    Items VARBINARY(6000) NULL,
    Money INT NULL
);

CREATE TABLE ExtWarehouse (
    AccountID VARCHAR(10) NOT NULL,
    AccountIndex INT NOT NULL,
    Number INT NOT NULL,
    Items VARBINARY(6000) NULL,
    Money INT NULL,
    PRIMARY KEY (AccountID, Number)
);

CREATE TABLE MEMB_INFO (
    memb_guid INT NOT NULL,
    memb___id VARCHAR(10) NOT NULL PRIMARY KEY,
    memb__pwd VARCHAR(10) NULL,
    memb_name VARCHAR(10) NULL,
    mail_addr VARCHAR(50) NULL,
    bloc_code CHAR(1) NULL,
    AccountLevel INT NULL,
    AccountExpireDate DATETIME NULL
);

CREATE TABLE MEMB_STAT (
    memb___id VARCHAR(10) NOT NULL PRIMARY KEY,
    ConnectStat INT NULL
);

CREATE TABLE Character (
    AccountID VARCHAR(10) NOT NULL,
    Name VARCHAR(10) NOT NULL,
    CharacterIndex BIGINT NOT NULL,
    cLevel INT NULL,
    Class INT NULL,
    ResetCount INT NULL,
    MasterResetCount INT NULL,
    Money INT NULL,
    LevelUpPoint INT NULL,
    Strength INT NULL,
    Dexterity INT NULL,
    Vitality INT NULL,
    Energy INT NULL,
    Leadership INT NULL,
    MapNumber INT NULL,
    PkCount INT NULL,
    PkLevel INT NULL,
    PRIMARY KEY (Name, CharacterIndex)
);

CREATE TABLE Guild (
    G_Name VARCHAR(8) NOT NULL PRIMARY KEY,
    G_Master VARCHAR(10) NULL,
    G_Score INT NULL,
    G_Count INT NULL,
    G_Notice VARCHAR(60) NULL,
    G_Mark VARBINARY(32) NULL
);

CREATE TABLE GuildMember (
    Name VARCHAR(10) NOT NULL PRIMARY KEY,
    G_Name VARCHAR(8) NOT NULL
);

CREATE TABLE MasterSkillTree (
    Name VARCHAR(50) NOT NULL,
    CharacterIndex BIGINT NOT NULL PRIMARY KEY,
    MasterLevel INT NULL,
    MasterPoint INT NULL,
    MasterExperience BIGINT NULL
);

CREATE TABLE EnhanceSkillTree (
    Name VARCHAR(50) NOT NULL,
    CharacterIndex BIGINT NOT NULL PRIMARY KEY,
    EnhancePoint INT NULL
);
