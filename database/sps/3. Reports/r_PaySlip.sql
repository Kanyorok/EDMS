CREATE or ALTER Procedure r_PaySlip
(

@Employee Varchar(200),
@SalaryPeriod VARCHAR(100)
)

AS 

Begin

SET NOCount ON 

SELECT Distinct 
    E.EmployeeID,
    LTRIM(RTRIM(
        E.FirstName + ' ' +
        ISNULL(E.MiddleName + ' ', '') +
        E.LastName
    )) AS EmployeeName,

    E.TaxID AS EmployeePin,
	B.BankName,
	BR.BranchName,
	EB.AccountNumber,
	D.Name AS Department,
	PR.RunNumber AS SalaryPeriod,
	EP.BasicPay,
	EA.Amount AS Allowances,
	EPR.TotalReliefs AS Reliefs,
	EPR.PayePayable AS PAYE,
	EPR.NetPay


 From t_Employees E

 LEFT JOIN  t_EmployeePayrollResults  EP  
 ON  E.ID=EP.EmployeeId

 LEFT JOIN  t_EmployeeBanks EB 
 ON E.ID=EB.EmployeeId

 LEFT JOIN t_PayrollRuns PR  
 ON    EP.PayrollRunID=PR.Id

 LEFT JOIN t_Departments D   
 ON  D.Id=E.Id

 LEFT JOIN t_BankBranches BR
 ON BR.BranchID=EB.BankBranchId

 LEFT JOIN t_Banks B 
 ON B.BankID = BR.BranchID

 LEFT JOIN t_EmployeeAllowances EA 
 ON EA.EmployeeId=EB.EmployeeId

 LEFT JOIN t_EmployeePayrollResults  EPR
 ON EPR.EmployeeId=EB.EmployeeId

 LEFT JOIN t_PayrollResultDeductions PDR
 ON PDR.Id = EPR.Id

 LEFT JOIN t_PayrollDeductions PD  
 ON PD.DeductionID=PDR.DeductionID

 WHERE
  @SalaryPeriod=PR.RunNumber
  AND @Employee=E.EmployeeID

SET NOCOUNT OFF;
END;


----select * from t_PayrollRuns