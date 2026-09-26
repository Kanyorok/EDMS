create  OR ALTER    PROCEDURE [dbo].[r_TripLogs]
(
    @Startdate DATE = NULL,
    @EndDate DATE = NULL
)
  
AS
BEGIN
    SET NOCOUNT ON;

    CREATE TABLE #TripLogs
    (
        TripNo        VARCHAR(200),
        Vehicle       VARCHAR(200),
        Driver        varchar(200),
        TripType    VARCHAR(200),
		LoadType    VARCHAR(200),
        StartDate     DATE,
        EndDate       DATE,
        StartLocation Varchar(200),
        EndLocation   Varchar(200),
        Purpose       Varchar(200)

    );

    INSERT INTO #TripLogs
    (
TripNo,
     Vehicle,
     Driver,
     TripType,
	 LoadType,
     StartDate,
     EndDate,
     StartLocation,
     EndLocation,
     Purpose)
    SELECT t.TripNo,
           v.RegistrationNo,
           d.DriverNo,
           dt.Description AS TripType,

		   lt.Description As LoadType,
           t.TripStartDate,
           t.TripEndDate,
           t.StartLocation,
           t.EndLocation,
           t.Purpose

    FROM t_TripLogs AS t
             join t_FleetVehicles AS v on v.Id = v.Id
             join t_ContractedDrivers AS d on 
d.Id = d.Id
     join t_CodeDetails AS dt on dt.ID = t.TripType
	  join t_CodeDetails AS lt on lt.ID = t.LoadType
     where @Startdate IS NULL OR t.TripStartDate >= @Startdate
       AND @EndDate IS NULL OR t.TripEndDate <= @EndDate;
  
    SELECT *
 FROM #TripLogs;

    DROP TABLE #TripLogs;
END


-- GO
-- EXEC r_TripLogs
