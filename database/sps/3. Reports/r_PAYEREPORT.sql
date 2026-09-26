CREATE OR ALTER PROCEDURE r_PAYEREPORT
(
---@EmployeeNo  Nvarchar (max)=NULL,
@PeriodFrom Smalldatetime =NULL,
@PeriodTo   Smalldatetime =NULL
)

AS
BEGIN

SET NOCOUNT ON


SELECT  Distinct 

    E.EmployeeID,

    LTRIM(RTRIM(
        E.FirstName + ' ' +
        ISNULL(E.MiddleName + ' ', '') +
        E.LastName
    )) AS EmployeeName,

    E.TaxID AS EmployeePin,

    EP.GrossPay      AS GrossPay,
    EP.TaxablePay    AS TaxablePay,
    EP.PayePayable   AS PAYEdeducted,
    EP.TotalReliefs  AS PersonalRelief,

    U.Name AS CreatedBy,
    E.CreatedOn,
    UM.Name AS ModifiedBy,
    E.ModifiedOn

FROM t_Employees E

INNER JOIN t_EmployeePayrollResults EP
    ON EP.EmployeeId = E.Id

INNER JOIN t_Users U
    ON E.CreatedBy = U.Id

INNER JOIN t_Users UM
    ON E.ModifiedBy = UM.Id

WHERE
    
   (@PeriodFrom IS NULL OR EP.CreatedOn >= @PeriodFrom)
      AND (@PeriodTo IS NULL OR EP.CreatedOn < DATEADD(DAY, 1, @PeriodTo))
      --AND (@Employeeid IS NULL OR EP.EmployeeId=@Employeeid);
	 -- AND (@EmployeeNo IS NULL OR @EmployeeNo = 'ALL' OR E.EmployeeID IN (SELECT value FROM STRING_SPLIT(@EmployeeNo, ',')))

SET NOCOUNT OFF;
END;


