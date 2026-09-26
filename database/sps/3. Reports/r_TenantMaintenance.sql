--Modified by Joe Muchai on 26/3/2026
CREATE OR ALTER  PROC [dbo].[r_TenantMaintenance]
AS
BEGIN
    CREATE TABLE #TenantMaintenance
    (	
		[No.]       INT,  
        TenantType  VARCHAR(200),
        TenantName  VARCHAR(200),
        ID          VARCHAR(200),
        Phone       VARCHAR(200),
        Email       VARCHAR(200),
        Remarks     VARCHAR(200),
        TradingName VARCHAR(200)
    )

    INSERT INTO #TenantMaintenance (
									[No.],
									TenantType,
                                    TenantName,
                                    ID,
                                    Phone,
                                    Email,
                                    Remarks,
                                    TradingName)
    SELECT 
			ROW_NUMBER() OVER (ORDER BY TP.ThirdPartyName ASC) AS [No.],
			CD.Description AS [TenantvType],
           TP.ThirdPartyName as [Tenant Name],
           Tp.RegistrationNumber as [Registration No] ,
			TP.Phone as [Phone Number],
           TP.email as [Email Address],
           TM.Remarks,
           TP.TradingName as [Trading Name]
    FROM t_TenantMaintenance AS TM
             JOIN t_CodeDetails CD ON TM.TenantType = CD.ID
			 JOIN t_ThirdParties TP on TP.Id=TM.ThirdPartyId


    SELECT * FROM #TenantMaintenance;

    DROP TABLE #TenantMaintenance;
END
--GO
--Exec [r_TenantMaintenance]

