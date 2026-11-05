:setvar ApplicationDatabase "MuOnline_Web"
:setvar ApplicationLogin "muonline_web_app"
:setvar ApplicationPassword "REPLACE_WITH_A_LONG_RANDOM_PASSWORD"

/*
    Run this fallback template with SQLCMD mode enabled after replacing every
    variable. Normal Docker first start performs the same work automatically.

    The runtime login can migrate and use only the isolated Laravel database.
    It receives no server role and no permission in the MuOnline game database.
*/

USE [master];
GO

IF DB_ID(N'$(ApplicationDatabase)') IS NULL
BEGIN
    CREATE DATABASE [$(ApplicationDatabase)];
END;
GO

IF SUSER_ID(N'$(ApplicationLogin)') IS NULL
BEGIN
    CREATE LOGIN [$(ApplicationLogin)]
        WITH PASSWORD = N'$(ApplicationPassword)',
        CHECK_POLICY = ON,
        CHECK_EXPIRATION = OFF;
END;
GO

USE [$(ApplicationDatabase)];
GO

IF USER_ID(N'$(ApplicationLogin)') IS NULL
BEGIN
    CREATE USER [$(ApplicationLogin)] FOR LOGIN [$(ApplicationLogin)]
        WITH DEFAULT_SCHEMA = [dbo];
END;
GO

IF DATABASE_PRINCIPAL_ID(N'muonline_web_application') IS NULL
BEGIN
    CREATE ROLE [muonline_web_application] AUTHORIZATION [dbo];
END;
GO

IF IS_ROLEMEMBER(N'muonline_web_application', N'$(ApplicationLogin)') <> 1
BEGIN
    ALTER ROLE [muonline_web_application] ADD MEMBER [$(ApplicationLogin)];
END;
GO

GRANT SELECT, INSERT, UPDATE, DELETE ON SCHEMA::[dbo] TO [muonline_web_application];
GRANT EXECUTE, REFERENCES, VIEW DEFINITION ON SCHEMA::[dbo] TO [muonline_web_application];
GRANT CREATE TABLE TO [muonline_web_application];
GRANT ALTER ON SCHEMA::[dbo] TO [muonline_web_application];
GO

/* The application role intentionally receives no server-level or MuOnline rights. */
