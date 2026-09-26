CREATE OR ALTER PROC [dbo].[r_LegalLoanSecurities]
    @FromDate DATE = NULL,
    @ToDate   DATE = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        L.Id,
        L.SecurityType,
        L.OwnerName,
        L.OwnerIDNumber,
        L.LoanAccountNumber,
        L.Value,
        L.Institution,
        L.RegistrationDetails,
        L.Locations,
        L.SecurityStatus,
        L.Remarks,
        U.Name AS CreatedBy,
        L.CreatedOn
    FROM t_LegalLoanSecurities L
    JOIN t_Users U ON U.Id = L.CreatedBy
    WHERE L.DeletedOn IS NULL
      AND (@FromDate IS NULL OR L.CreatedOn >= @FromDate)
      AND (@ToDate   IS NULL OR L.CreatedOn < DATEADD(DAY, 1, @ToDate));
END
