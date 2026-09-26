--Modified by Joe Muchai on 27/03/2026
CREATE OR ALTER PROC [dbo].[r_InsuranceProductRiders] 

AS
BEGIN
    SET NOCOUNT ON;

    CREATE TABLE #InsuranceProductRiders
    (
        InsuranceProvider   VARCHAR(200),
        InsuranceProduct    VARCHAR(200),
        RiderName           VARCHAR(200),
        Description         VARCHAR(300),
        AdditionalPremium   VARCHAR(200),
        IsOptional          VARCHAR(200),
        --IsActive            VARCHAR(200),
        CreatedBy           VARCHAR(300),
        CreatedOn           DATE
    )

    INSERT INTO #InsuranceProductRiders
    (
        InsuranceProvider,
        InsuranceProduct,
        RiderName,
        Description,
        AdditionalPremium,
        IsOptional,
        CreatedBy,
        CreatedOn
    )
    SELECT 
        I.Name          AS [Insurance Provider],
        N.Name          AS [Insurance Product],
        R.RiderName     AS [Rider Name],
        R.Description,
        R.AdditionalPremium,
        CASE R.IsOptional
            WHEN 1 THEN 'Yes'
            WHEN 0 THEN 'No'
            ELSE 'Unknown'
        END AS IsOptional,
        U.Name          AS CreatedBy,
        R.CreatedOn
    FROM t_InsuranceProductRiders AS R
   
    INNER JOIN t_Users AS U ON U.Id = R.CreatedBy
     INNER JOIN t_InsuranceProducts AS N ON N.Id = R.Product
	  INNER JOIN t_InsuranceProviders AS I ON I.Id = N.Id 


    SELECT * FROM #InsuranceProductRiders;

    DROP TABLE #InsuranceProductRiders;
END
--GO
--exec r_InsuranceProductRiders
