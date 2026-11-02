:setvar GameDatabase "MuOnline"
:setvar GameWebLogin "muonline_web"
:setvar GameWebPassword "REPLACE_WITH_A_LONG_RANDOM_PASSWORD"

/*
    Run this template with SQLCMD mode enabled after replacing every variable.
    The login receives only the object permissions used by the 97d+99i adapter.
*/

USE [master];
GO

IF SUSER_ID(N'$(GameWebLogin)') IS NULL
BEGIN
    CREATE LOGIN [$(GameWebLogin)]
        WITH PASSWORD = N'$(GameWebPassword)',
        CHECK_POLICY = ON,
        CHECK_EXPIRATION = OFF;
END;
GO

USE [$(GameDatabase)];
GO

IF USER_ID(N'$(GameWebLogin)') IS NULL
BEGIN
    CREATE USER [$(GameWebLogin)] FOR LOGIN [$(GameWebLogin)];
END;
GO

IF DATABASE_PRINCIPAL_ID(N'muonline_web_application') IS NULL
BEGIN
    CREATE ROLE [muonline_web_application] AUTHORIZATION [dbo];
END;
GO

IF IS_ROLEMEMBER(N'muonline_web_application', N'$(GameWebLogin)') <> 1
BEGIN
    ALTER ROLE [muonline_web_application] ADD MEMBER [$(GameWebLogin)];
END;
GO

GRANT SELECT ON OBJECT::[dbo].[MEMB_INFO] TO [muonline_web_application];
GRANT SELECT ON OBJECT::[dbo].[MEMB_STAT] TO [muonline_web_application];
GRANT SELECT ON OBJECT::[dbo].[Character] TO [muonline_web_application];
GRANT SELECT ON OBJECT::[dbo].[warehouse] TO [muonline_web_application];
GRANT SELECT ON OBJECT::[dbo].[Guild] TO [muonline_web_application];
GRANT SELECT ON OBJECT::[dbo].[GuildMember] TO [muonline_web_application];
GRANT SELECT ON OBJECT::[dbo].[VI_CURR_INFO] TO [muonline_web_application];
GRANT SELECT ON OBJECT::[dbo].[GameServerInfo] TO [muonline_web_application];

GRANT INSERT ON OBJECT::[dbo].[MEMB_INFO] TO [muonline_web_application];
GRANT INSERT ON OBJECT::[dbo].[VI_CURR_INFO] TO [muonline_web_application];

GRANT UPDATE ON OBJECT::[dbo].[MEMB_INFO] TO [muonline_web_application];
GRANT UPDATE ON OBJECT::[dbo].[Character] TO [muonline_web_application];
GRANT UPDATE ON OBJECT::[dbo].[warehouse] TO [muonline_web_application];

GRANT EXECUTE ON OBJECT::[dbo].[WZ_GetItemSerial] TO [muonline_web_application];
GRANT VIEW DEFINITION ON OBJECT::[dbo].[WZ_GetItemSerial] TO [muonline_web_application];
GO

/* The application role intentionally receives no DELETE, DDL, db_owner or sysadmin rights. */
