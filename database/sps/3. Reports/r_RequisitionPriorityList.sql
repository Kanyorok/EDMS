---Amended by Joe Muchai 16/03/26
CREATE OR ALTER   PROCEDURE r_RequisitionPriorityList
    @FromDate SmallDateTime = NULL,
    @ToDate   SmallDateTime = NULL
AS
BEGIN
    
    SET NOCOUNT ON;

	CREATE TABLE #reqprioritylisting
	(
	ID int identity(1,1),
	ProcurementPlan		VARCHAR(200),
	ReferenceNumber		VARCHAR(200),
	RequisitionNo		VARCHAR(200),
	RequisitionDate		VARCHAR(200),
	RequestedBy			VARCHAR(200)
	)
	INSERT INTO #reqprioritylisting
	(

	ProcurementPlan	,
	ReferenceNumber	,
	RequisitionNo,
	RequisitionDate	,
	RequestedBy	
	)
    SELECT 
	
        CP.Title AS ProcurementPlan,
        CP.ReferenceNumber,
        R.RequisitionNo,
        R.CreatedOn AS RequisitionDate,
        U.Name AS RequestedBy
    FROM t_ConsolidatedProcurementPlan CP
    INNER JOIN t_Requisitions R ON CP.PlanID = R.PlanRef 
    INNER JOIN t_users U ON U.Id = CP.CreatedBy
    WHERE 
        (@FromDate IS NULL OR R.CreatedOn >= @FromDate)
        AND 
        (@ToDate IS NULL OR R.CreatedOn <= @ToDate)
    ORDER BY R.CreatedOn DESC;

	select * from #reqprioritylisting
END
--go
--exec r_RequisitionPriorityList

