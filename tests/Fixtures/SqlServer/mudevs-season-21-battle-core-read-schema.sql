-- Sanitized portable projection of the private BattleCore account read sources.
CREATE TABLE BattleCoreUser (
    UniqueKey INT NOT NULL PRIMARY KEY,
    AccountID VARCHAR(10) NULL,
    Name VARCHAR(10) NULL,
    ServerCode SMALLINT NOT NULL,
    RegisterState SMALLINT NOT NULL,
    RegisterMonth SMALLINT NOT NULL,
    RegisterDay SMALLINT NOT NULL
);

CREATE TABLE BattleCoreReward (
    id INT NOT NULL PRIMARY KEY,
    AccountID VARCHAR(10) NULL,
    RealName VARCHAR(10) NULL,
    ServerCode SMALLINT NOT NULL,
    ItemCount SMALLINT NOT NULL,
    TookState TINYINT NOT NULL
);
