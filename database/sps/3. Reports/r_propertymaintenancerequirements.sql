--Modified by Joe Muchai 26/03/26
CREATE OR ALTER PROCEDURE [dbo].[r_PropertyMaintenanceRequirements](
    @FromDate DATETIME = NULL,
    @ToDate DATETIME = NULL
)
AS
BEGIN
    
    SET @FromDate = ISNULL(@FromDate, '1900-01-01')
    SET @ToDate = ISNULL(@ToDate, '2099-12-31')

    SELECT 
        MN.RequestNumber,
        PR.PropertyName AS [Property],
        TP.ThirdPartyName AS [ReportedBy],
        MN.CreatedOn,
        CD.Description AS [IssueType],
        C.Description AS [Priority]
    FROM t_MaintenanceRequest AS MN
    JOIN t_PropertyRegistry PR ON MN.Property = PR.Id
    JOIN t_CodeDetails C ON MN.Priority = C.ID AND C.CodeID = 'PriorityLevel'
    LEFT JOIN t_CodeDetails CD ON MN.IssueType = CD.ID AND CD.CodeID = 'IssueType'
    LEFT JOIN t_ThirdParties TP ON TP.Id = MN.ReportedBy -- Changed to LEFT JOIN just in case
    WHERE MN.CreatedOn >= @FromDate AND MN.CreatedOn < DATEADD(day, 1, @ToDate)
    ORDER BY MN.RequestNumber
END
--GO
--Exec r_PropertyMaintenanceRequirements


