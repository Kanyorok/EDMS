--Modified by Joe Muchai on 26/3/2026
CREATE OR ALTER  PROC [dbo].[r_RegisteredProperties] 
@FromDate SMALLDATETIME=NULL,
@ToDate SMALLDATETIME = NULL
AS
BEGIN
    CREATE TABLE #RegisteredProperties
    (
		[No.]       INT,     
        PropertyName        VARCHAR(200),
	    PropertyCode        VARCHAR(200),
        PropertyType        VARCHAR(100),
        Category            VARCHAR(100),
        Owner               VARCHAR(150),
        AcquisitionDate     DATE,
        Country             VARCHAR(100),
        TownCity            VARCHAR(100),
        PropertyDescription VARCHAR(300),
        CreatedBy           VARCHAR(50),
        CreatedOn           DATE

    )
    INSERT INTO #RegisteredProperties
    SELECT
			ROW_NUMBER() OVER (ORDER BY P.PropertyTypeName ASC) AS [No.], 
			PR.PropertyName,
           PR.PropertyCode,
		   P.PropertyTypeName as PropertyType,
           C.Name             as Category,
           PR.Owner,
           PR.AcquisitionDate,
           CT.Name as Country,
           T.Name             as TownCity,
           PR.PropertyDescription,
           U.Name   
           as CreatedBy,
           PR.CreatedOn

    FROM t_Propertyregistry PR
             JOIN t_users U ON U.ID = PR.CreatedBy
             JOIN t_CategoryMaster C ON C.ID = PR.Category
             JOIN t_localities T ON T.ID = PR.LocationId
			 JOIN t_propertytype P on P.ID = PR.PropertyType
			 JOIN t_Countries CT on ct.Id=PR.CountryId

    WHERE (@FromDate IS NULL OR PR.CreatedOn >= @FromDate)
      AND (@ToDate IS NULL OR PR.CreatedOn < DATEADD(DAY, 1, @ToDate))

    SELECT * FROM #RegisteredProperties
END

--GO
--EXEC R_RegisteredProperties


