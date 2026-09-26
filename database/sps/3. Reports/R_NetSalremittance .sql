--SET ANSI_NULLS ON
--GO
--SET QUOTED_IDENTIFIER ON

CREATE OR ALTER PROCEDURE R_NetSalremittance 
(
@SalaryPeriod VARCHAR(100)
)
AS
BEGIN

	SET NOCOUNT ON;

	SELECT 
	E.EmployeeID AS [Emp No],
	ISNULL(E.FirstName,'') ++ ' ' ++ ISNULL(E.LastName,'') ++ '' ++ ISNULL( E.MiddleName,'') AS [Employee Name],
	CAST(EB.AccountNumber AS VARCHAR(50)) AS[Account No],
	--B.BankName Bank,
	--TEST DATA  START
	CASE
	WHEN E.EmployeeID='e00003' THEN 'ABSA'
	WHEN E.EmployeeID='e00004' THEN 'KCB'
	WHEN E.EmployeeID='e00005' THEN 'I & M'
	WHEN E.EmployeeID='e00007' THEN 'KCB'
	WHEN E.EmployeeID='e00008' THEN 'Family Bank'
	ELSE 'Equity Bank'
	END AS Bank
	,
	-- TEST DATA END
	BB.BranchName AS Branch,
	PR.NetPay AS [Amount(KES)]
	FROM  t_EmployeePayrollResults(NOLOCK) PR
	INNER JOIN t_Employees(NOLOCK) E
	ON PR.EmployeeId=E.Id
	LEFT  JOIN t_EmployeeBanks(NOLOCK) EB
	ON EB.EmployeeId=E.ID
	LEFT JOIN t_BankBranches(NOLOCK) BB
	ON BB.BranchID=EB.BankBranchId
	LEFT JOIN t_Banks (NOLOCK)B
	ON BB.BankID=B.BankID
	WHERE PR.PayrollRunID=@SalaryPeriod



END
