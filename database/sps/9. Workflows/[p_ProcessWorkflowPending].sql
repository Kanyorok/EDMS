CREATE OR ALTER PROCEDURE [dbo].[p_ProcessWorkflowPending]
    @Source NVARCHAR(255) = NULL,
    @SourceID NVARCHAR(100) = NULL,
    @StageID BIGINT = NULL,
    @Amount DECIMAL(20,4) = NULL,
    @SpecificUserIds NVARCHAR(MAX) = NULL,
    @BranchId BIGINT = NULL,
    @PermissionIdOverride NVARCHAR(MAX) = NULL,
    -- The t_CodeDetails.ID that the caller used to record the "Submitted"
    -- history row for THIS module (each domain has its own CodeID group -
    -- e.g. ThirdPartyApprovalStatus for Dealers/Suppliers, DisposalPlanApprovalStatus
    -- for Disposal Plans - so there is no single global "Submitted" id).
    -- When passed, this is used as-is instead of guessing a module-agnostic
    -- id via a hardcoded Description lookup, which only ever matched the
    -- generic engine's own codes and silently no-op'd for every other module.
    -- NOTE: named distinctly from the local @SubmittedStatusId variable below -
    -- T-SQL identifiers are case-insensitive, so a name differing only by case
    -- would collide with it.
    @SubmittedStatusIdOverride BIGINT = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE
        @Now DATETIME = GETDATE(),
        @SystemUserId BIGINT,
        @SubmittedStatusId BIGINT,
        @ApprovedStatusId BIGINT,
        @RejectedStatusId BIGINT,
        @ProcessedCount INT = 0,
        @ErrorCount INT = 0,
        -- Batch loop variables
        @BNotifyId BIGINT,
        @BNotifyEmail NVARCHAR(255),
        @BNotifyName NVARCHAR(255),
        @BSubject NVARCHAR(255),
        @BMessage NVARCHAR(MAX);

    DECLARE @BatchEligible TABLE (
        Id BIGINT PRIMARY KEY,
        Name NVARCHAR(255),
        Email NVARCHAR(255)
    );

    -- =====================================================================
    -- 0. RESOLVE SYSTEM LOOKUPS
    -- =====================================================================
    SELECT TOP 1 @SystemUserId = Id
    FROM t_Users WITH (NOLOCK)
    WHERE UserID = 'ERPSYS' AND DeletedOn IS NULL;

    IF @SystemUserId IS NULL
    BEGIN
        RAISERROR('System user (ERPSYS) not found.', 16, 1);
        RETURN;
    END

    SELECT @SubmittedStatusId = Id FROM t_CodeDetails WITH (NOLOCK) WHERE [Description] = 'Submitted for Approval';
    SELECT @ApprovedStatusId  = Id FROM t_CodeDetails WITH (NOLOCK) WHERE [Description] = 'Approved';
    SELECT @RejectedStatusId  = Id FROM t_CodeDetails WITH (NOLOCK) WHERE [Description] = 'Rejected';

    IF @SubmittedStatusId IS NULL OR @ApprovedStatusId IS NULL OR @RejectedStatusId IS NULL
    BEGIN
        RAISERROR('Required workflow status codes not found in t_CodeDetails (Submitted for Approval, Approved, Rejected).', 16, 1);
        RETURN;
    END

    -- =====================================================================
    -- MODE A: PROCESS SPECIFIC RECORD
    -- =====================================================================
    IF @Source IS NOT NULL AND @SourceID IS NOT NULL AND @StageID IS NOT NULL
    BEGIN
        DECLARE
            @PermissionId BIGINT,
            @WorkflowType NVARCHAR(50),
            @CountRequired INT,
            @MakerId BIGINT,
            @DocOwnerId BIGINT = NULL,
            @WorkFlowID BIGINT,
            @ItemLabel NVARCHAR(255),
            @StageName NVARCHAR(255),
            @StagePermissionName NVARCHAR(255),
            @StagePermissionId BIGINT,
            @EffectivePermissionId BIGINT,
            @EffectivePermissionIds NVARCHAR(MAX) = NULL,
            @StageSpecificUserIds NVARCHAR(MAX) = NULL,
            @EffectiveSpecificUserIds NVARCHAR(MAX) = NULL,
            @StageBranchId BIGINT = NULL,
            @EffectiveBranchId BIGINT = NULL,
            @StageOverridePermissionId BIGINT = NULL,
            @LimitType NVARCHAR(20) = 'DEFAULT',
            @TierMaxAmount DECIMAL(20,4) = NULL,
            @EligibleCount INT = 0;

        -- =================================================================
        -- A1. GET STAGE DETAILS
        -- =================================================================
        SELECT
            @PermissionId = s.PermissionId,
            @WorkflowType = wt.TypeId,
            @CountRequired = ISNULL(s.[Count], 1),
            @WorkFlowID = s.WorkFlowId,
            @ItemLabel = CASE
                WHEN NULLIF(LTRIM(RTRIM(w.Name)), '') IS NULL
                  OR LTRIM(RTRIM(w.Name)) = @Source
                  OR LTRIM(RTRIM(w.Name)) LIKE 't[_]%'
                THEN 'workflow item'
                ELSE LTRIM(RTRIM(w.Name))
            END,
            @StageName = s.StageName,
            @StageSpecificUserIds = s.SpecificUserIds,
            @StageBranchId = s.BranchId,
            @StageOverridePermissionId = s.OverridePermissionId
        FROM t_WorkFlowStages s WITH (NOLOCK)
        LEFT JOIN t_WorkFlowTypes wt WITH (NOLOCK) ON s.WorkFlowTypeId = wt.Id
        LEFT JOIN t_WorkFlows w WITH (NOLOCK) ON s.WorkFlowId = w.Id AND w.DeletedOn IS NULL
        WHERE s.Id = @StageID AND s.DeletedOn IS NULL;

        IF @PermissionId IS NULL
        BEGIN
            DECLARE @StageErrMsg NVARCHAR(200) = 'Stage not found or has no permission for StageID: ' + CAST(@StageID AS NVARCHAR(50));
            RAISERROR(@StageErrMsg, 16, 1);
            RETURN;
        END

        -- =================================================================
        -- A2. GET SUBMITTER FOR MAKER-CHECKER
        -- =================================================================
        -- The maker is whoever submitted the CURRENT pending cycle - not
        -- whoever owns the oldest history row for this record. A record's
        -- workflow history can span more than one submit/approve/reject
        -- cycle (e.g. rejected-and-resubmitted), so ORDER BY CreatedOn ASC
        -- picks a stale submitter once a record has been through more than
        -- one cycle, silently letting the real (current) submitter through
        -- as an eligible approver of their own request. Scope to the latest
        -- "Submitted for Approval" entry specifically, not just the latest
        -- history row of any type (which could be a checker's own prior
        -- approval/rejection, wrongly flagging the checker as the maker).
        SELECT TOP 1 @MakerId = CreatedBy
        FROM t_WorkFlowHistory WITH (NOLOCK)
        WHERE Source = @Source
          AND SourceID = @SourceID
          AND StatusId = @SubmittedStatusId
          AND DeletedOn IS NULL
        ORDER BY CreatedOn DESC;

        -- Maker-checker must key off who OWNS the record, not who happened to
        -- click "submit for approval" - anyone with access can submit someone
        -- else's draft, and @MakerId above only captures the submitter. Look
        -- up the source row's own CreatedBy directly (every t_* table in this
        -- app follows the CreatedBy/Id convention) so the actual creator is
        -- excluded even when a different user submitted it on their behalf.
        -- Best-effort: falls back to submitter-only exclusion if the source
        -- table doesn't follow the convention, rather than failing the call.
        BEGIN TRY
            IF EXISTS (
                SELECT 1 FROM sys.columns
                WHERE object_id = OBJECT_ID(QUOTENAME(@Source)) AND name = 'CreatedBy'
            )
            BEGIN
                DECLARE @OwnerLookupSql NVARCHAR(MAX) =
                    N'SELECT @DocOwnerIdOut = CreatedBy FROM ' + QUOTENAME(@Source) +
                    N' WHERE Id = TRY_CAST(@SourceIDParam AS BIGINT)';

                EXEC sp_executesql
                    @OwnerLookupSql,
                    N'@SourceIDParam NVARCHAR(100), @DocOwnerIdOut BIGINT OUTPUT',
                    @SourceIDParam = @SourceID,
                    @DocOwnerIdOut = @DocOwnerId OUTPUT;
            END
        END TRY
        BEGIN CATCH
            SET @DocOwnerId = NULL;
        END CATCH

        -- =================================================================
        -- A3. AMOUNT-BASED TIER SELECTION
        -- =================================================================
        SET @StagePermissionName = 'workflowstage_' + REPLACE(ISNULL(@StageName, ''), ' ', '');
        SET @StagePermissionId = NULL;

        SELECT TOP 1 @StagePermissionId = p.Id
        FROM t_Permissions p WITH (NOLOCK)
        WHERE p.Name = @StagePermissionName;

        -- Prefer stage-scoped permission when present; fall back to configured stage PermissionId.
        SET @EffectivePermissionId = ISNULL(@StagePermissionId, @PermissionId);
        SET @EffectivePermissionIds = NULLIF(LTRIM(RTRIM(@PermissionIdOverride)), '');
        SET @EffectivePermissionId = COALESCE(TRY_CAST(@EffectivePermissionIds AS BIGINT), @StageOverridePermissionId, @EffectivePermissionId);
        SET @EffectivePermissionIds = COALESCE(@EffectivePermissionIds, CAST(@StageOverridePermissionId AS NVARCHAR(30)), CAST(@EffectivePermissionId AS NVARCHAR(30)));
        SET @EffectiveSpecificUserIds = COALESCE(NULLIF(LTRIM(RTRIM(@SpecificUserIds)), ''), @StageSpecificUserIds);
        SET @EffectiveBranchId = COALESCE(@BranchId, @StageBranchId);

        IF ISNULL(@WorkflowType, '') = 'AMT'
           AND @Amount IS NOT NULL
           AND @Amount > 0
           AND @EffectivePermissionIds NOT LIKE '%,%'
           AND @StageOverridePermissionId IS NULL
        BEGIN
            IF EXISTS (
                SELECT 1 FROM t_WorkFlowLimits WITH (NOLOCK)
                WHERE WorkFlowStageId = @StageID AND DeletedOn IS NULL
            )
            BEGIN
                SELECT TOP 1
                    @EffectivePermissionId = PermissionId,
                    @TierMaxAmount = MaxAmount,
                    @LimitType = LimitType
                FROM dbo.f_getPermissionForAmount(@StageID, @Amount);

                -- Fallback to stage default if function returns nothing
                IF @EffectivePermissionId IS NULL
                    SET @EffectivePermissionId = ISNULL(@StagePermissionId, @PermissionId);
            END
        END

        -- =================================================================
        -- A4. GET ELIGIBLE USERS
        -- =================================================================
        DECLARE @EligibleUsers TABLE (
            Id BIGINT PRIMARY KEY,
            Name NVARCHAR(255),
            Email NVARCHAR(255)
        );

        INSERT INTO @EligibleUsers (Id, Name, Email)
        SELECT DISTINCT u.Id, u.Name, u.Email
        FROM t_Users u WITH (NOLOCK)
        WHERE u.DeletedOn IS NULL
          AND u.Id <> ISNULL(@MakerId, 0)
          AND u.Id <> ISNULL(@DocOwnerId, 0)
          AND u.UserID <> 'CSADM'
          AND EXISTS (
              SELECT 1
              FROM STRING_SPLIT(REPLACE(REPLACE(REPLACE(@EffectivePermissionIds, '[', ''), ']', ''), '"', ''), ',') permission_ids
              CROSS APPLY dbo.f_getUserWithPermission(TRY_CAST(LTRIM(RTRIM(permission_ids.value)) AS BIGINT)) perm
              WHERE TRY_CAST(LTRIM(RTRIM(permission_ids.value)) AS BIGINT) IS NOT NULL
                AND perm.Id = u.Id
          )
          AND (
              @EffectiveSpecificUserIds IS NULL
              OR EXISTS (
                  SELECT 1
                  FROM STRING_SPLIT(
                      REPLACE(REPLACE(REPLACE(@EffectiveSpecificUserIds, '[', ''), ']', ''), '"', ''),
                      ','
                  ) ids
                  WHERE TRY_CAST(LTRIM(RTRIM(ids.value)) AS BIGINT) = u.Id
              )
          )
          AND (
              @EffectiveBranchId IS NULL
              OR TRY_CAST(u.BranchId AS BIGINT) = @EffectiveBranchId
              OR EXISTS (
                  SELECT 1
                  FROM t_ModelRoles mr WITH (NOLOCK)
                  INNER JOIN t_RolePermissions rp WITH (NOLOCK) ON mr.role_id = rp.role_id
                  WHERE TRY_CAST(mr.model_id AS BIGINT) = u.Id
                    AND mr.model_type = 'UserID'
                    AND mr.BranchId = @EffectiveBranchId
                    AND rp.permission_id IN (
                        SELECT TRY_CAST(LTRIM(RTRIM(permission_ids.value)) AS BIGINT)
                        FROM STRING_SPLIT(REPLACE(REPLACE(REPLACE(@EffectivePermissionIds, '[', ''), ']', ''), '"', ''), ',') permission_ids
                        WHERE TRY_CAST(LTRIM(RTRIM(permission_ids.value)) AS BIGINT) IS NOT NULL
                    )
                    AND mr.DeletedOn IS NULL
              )
          )
          AND NOT EXISTS (
              SELECT 1 FROM dbo.t_WorkFlowHistory h WITH (NOLOCK)
              WHERE h.Source = @Source
                AND h.SourceID = @SourceID
                AND h.Stage = CAST(@StageID AS NVARCHAR(200))
                AND h.CreatedBy = u.Id
                AND h.StatusId IN (@ApprovedStatusId, @RejectedStatusId)
                AND h.DeletedOn IS NULL
          );

        SET @EligibleCount = (SELECT COUNT(*) FROM @EligibleUsers);

        -- AMT fallback chain:
        -- 1) amount-tier permission selected above
        -- 2) stage-scoped permission (workflowstage_<StageName>)
        -- 3) configured stage permission on t_WorkFlowStages.PermissionId
        -- This prevents dead-ends when tier permissions are configured but have no assigned users.
        IF @EligibleCount = 0 AND ISNULL(@WorkflowType, '') = 'AMT'
        BEGIN
            IF @StagePermissionId IS NOT NULL AND @StagePermissionId <> @EffectivePermissionId
            BEGIN
                SET @EffectivePermissionId = @StagePermissionId;
                DELETE FROM @EligibleUsers;

                INSERT INTO @EligibleUsers (Id, Name, Email)
                SELECT DISTINCT u.Id, u.Name, u.Email
                FROM t_Users u WITH (NOLOCK)
                WHERE u.DeletedOn IS NULL
                  AND u.Id <> ISNULL(@MakerId, 0)
          AND u.Id <> ISNULL(@DocOwnerId, 0)
                  AND u.UserID <> 'CSADM'
                  AND EXISTS (
                      SELECT 1 FROM dbo.f_getUserWithPermission(@EffectivePermissionId) perm
                      WHERE perm.Id = u.Id
                  )
                  AND (
                      @EffectiveSpecificUserIds IS NULL
                      OR EXISTS (
                          SELECT 1
                          FROM STRING_SPLIT(
                              REPLACE(REPLACE(REPLACE(@EffectiveSpecificUserIds, '[', ''), ']', ''), '"', ''),
                              ','
                          ) ids
                          WHERE TRY_CAST(LTRIM(RTRIM(ids.value)) AS BIGINT) = u.Id
                      )
                  )
                  AND (
                      @EffectiveBranchId IS NULL
                      OR TRY_CAST(u.BranchId AS BIGINT) = @EffectiveBranchId
                      OR EXISTS (
                          SELECT 1
                          FROM t_ModelRoles mr WITH (NOLOCK)
                          INNER JOIN t_RolePermissions rp WITH (NOLOCK) ON mr.role_id = rp.role_id
                          WHERE TRY_CAST(mr.model_id AS BIGINT) = u.Id
                            AND mr.model_type = 'UserID'
                            AND mr.BranchId = @EffectiveBranchId
                            AND rp.permission_id = @EffectivePermissionId
                            AND mr.DeletedOn IS NULL
                      )
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM dbo.t_WorkFlowHistory h WITH (NOLOCK)
                      WHERE h.Source = @Source
                        AND h.SourceID = @SourceID
                        AND h.Stage = CAST(@StageID AS NVARCHAR(200))
                        AND h.CreatedBy = u.Id
                        AND h.StatusId IN (@ApprovedStatusId, @RejectedStatusId)
                        AND h.DeletedOn IS NULL
                  );

                SET @EligibleCount = (SELECT COUNT(*) FROM @EligibleUsers);
            END

            IF @EligibleCount = 0 AND @PermissionId IS NOT NULL AND @PermissionId <> @EffectivePermissionId
            BEGIN
                SET @EffectivePermissionId = @PermissionId;
                DELETE FROM @EligibleUsers;

                INSERT INTO @EligibleUsers (Id, Name, Email)
                SELECT DISTINCT u.Id, u.Name, u.Email
                FROM t_Users u WITH (NOLOCK)
                WHERE u.DeletedOn IS NULL
                  AND u.Id <> ISNULL(@MakerId, 0)
          AND u.Id <> ISNULL(@DocOwnerId, 0)
                  AND u.UserID <> 'CSADM'
                  AND EXISTS (
                      SELECT 1 FROM dbo.f_getUserWithPermission(@EffectivePermissionId) perm
                      WHERE perm.Id = u.Id
                  )
                  AND (
                      @EffectiveSpecificUserIds IS NULL
                      OR EXISTS (
                          SELECT 1
                          FROM STRING_SPLIT(
                              REPLACE(REPLACE(REPLACE(@EffectiveSpecificUserIds, '[', ''), ']', ''), '"', ''),
                              ','
                          ) ids
                          WHERE TRY_CAST(LTRIM(RTRIM(ids.value)) AS BIGINT) = u.Id
                      )
                  )
                  AND (
                      @EffectiveBranchId IS NULL
                      OR TRY_CAST(u.BranchId AS BIGINT) = @EffectiveBranchId
                      OR EXISTS (
                          SELECT 1
                          FROM t_ModelRoles mr WITH (NOLOCK)
                          INNER JOIN t_RolePermissions rp WITH (NOLOCK) ON mr.role_id = rp.role_id
                          WHERE TRY_CAST(mr.model_id AS BIGINT) = u.Id
                            AND mr.model_type = 'UserID'
                            AND mr.BranchId = @EffectiveBranchId
                            AND rp.permission_id = @EffectivePermissionId
                            AND mr.DeletedOn IS NULL
                      )
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM dbo.t_WorkFlowHistory h WITH (NOLOCK)
                      WHERE h.Source = @Source
                        AND h.SourceID = @SourceID
                        AND h.Stage = CAST(@StageID AS NVARCHAR(200))
                        AND h.CreatedBy = u.Id
                        AND h.StatusId IN (@ApprovedStatusId, @RejectedStatusId)
                        AND h.DeletedOn IS NULL
                  );

                SET @EligibleCount = (SELECT COUNT(*) FROM @EligibleUsers);
            END
        END

        IF @EligibleCount = 0
        BEGIN
            DECLARE @NoEligibleErr NVARCHAR(500) =
                'No eligible approvers found for this stage. Tier/EffectivePermissionId: ' +
                ISNULL(CAST(@EffectivePermissionId AS NVARCHAR(30)), 'NULL') +
                ', StagePermissionId: ' + ISNULL(CAST(@StagePermissionId AS NVARCHAR(30)), 'NULL') +
                ', ConfiguredStagePermissionId: ' + ISNULL(CAST(@PermissionId AS NVARCHAR(30)), 'NULL') +
                ', BranchId: ' + ISNULL(CAST(@EffectiveBranchId AS NVARCHAR(30)), 'NULL') +
                ', SpecificUserIds: ' + ISNULL(@EffectiveSpecificUserIds, 'NULL');

            RAISERROR(@NoEligibleErr, 16, 1);
            RETURN;
        END

        -- =================================================================
        -- A5. CREATE PENDING APPROVALS (transactional)
        -- =================================================================
        BEGIN TRY
            BEGIN TRANSACTION;

            -- Soft-delete existing pending for this source+stage (preserve audit trail)
            UPDATE dbo.t_WorkFlowPending
            SET DeletedBy = @SystemUserId,
                DeletedOn = @Now,
                ModifiedBy = @SystemUserId,
                ModifiedOn = @Now
            WHERE Source = @Source
              AND SourceID = @SourceID
              AND Stage = CAST(@StageID AS NVARCHAR(200))
              AND DeletedOn IS NULL;

            -- Insert pending for each eligible user
            INSERT INTO t_WorkFlowPending (
                Source, SourceID, Stage, UserId,
                CreatedBy, CreatedOn, ModifiedBy, ModifiedOn
            )
            SELECT
                @Source,
                @SourceID,
                CAST(@StageID AS NVARCHAR(200)),
                e.Id,
                @SystemUserId,
                @Now,
                @SystemUserId,
                @Now
            FROM @EligibleUsers e;

            SET @ProcessedCount = @@ROWCOUNT;

            -- Mark history as processed (isApproved = 0 means "pending action created").
            -- Prefer the caller-supplied status id (the exact CodeDetail the module used
            -- to record its own "Submitted" state) over the generic @SubmittedStatusId
            -- guess, which only matches the shared engine's own status codes.
            UPDATE t_WorkFlowHistory
            SET isApproved = 0,
                ModifiedBy = @SystemUserId,
                ModifiedOn = @Now
            WHERE Source = @Source
              AND SourceID = @SourceID
              AND Stage = CAST(@StageID AS NVARCHAR(200))
              AND StatusId = COALESCE(@SubmittedStatusIdOverride, @SubmittedStatusId)
              AND DeletedOn IS NULL
              AND isApproved IS NULL;

            COMMIT TRANSACTION;
        END TRY
        BEGIN CATCH
            IF @@TRANCOUNT > 0
                ROLLBACK TRANSACTION;

            DECLARE @SpecificError NVARCHAR(4000) = ERROR_MESSAGE();
            RAISERROR(@SpecificError, 16, 1);
            RETURN;
        END CATCH

        -- =================================================================
        -- A6. SEND NOTIFICATIONS (outside transaction)
        -- =================================================================
        DECLARE
            @NotifyUserId BIGINT,
            @NotifyEmail NVARCHAR(255),
            @NotifyName NVARCHAR(255),
            @EmailSubject NVARCHAR(255),
            @EmailMessage NVARCHAR(MAX);

        DECLARE notify_cursor CURSOR LOCAL FAST_FORWARD FOR
            SELECT Id, Email, Name FROM @EligibleUsers;

        OPEN notify_cursor;
        FETCH NEXT FROM notify_cursor INTO @NotifyUserId, @NotifyEmail, @NotifyName;

        WHILE @@FETCH_STATUS = 0
        BEGIN
            BEGIN TRY
                IF @NotifyEmail IS NOT NULL AND LEN(@NotifyEmail) > 5
                BEGIN
                    SET @EmailSubject = 'Approval Required: ' + @ItemLabel;
                    SET @EmailMessage =
                        'Dear ' + ISNULL(@NotifyName, 'User') + ',' + CHAR(13) + CHAR(10) +
                        CHAR(13) + CHAR(10) +
                        'An Item in the ' + @ItemLabel + ' requires your approval.' + CHAR(13) + CHAR(10) +
                        'Please log in to review and take action.';

                    EXEC p_sendNotificationEmail
                        @UserID = @NotifyUserId,
                        @Subject = @EmailSubject,
                        @Message = @EmailMessage,
                        @SenderId = @SystemUserId,
                        @Source = @Source,
                        @SourceID = @SourceID;
                END
            END TRY
            BEGIN CATCH
                -- Email failure should not fail the workflow
                SET @ErrorCount += 1;
            END CATCH

            FETCH NEXT FROM notify_cursor INTO @NotifyUserId, @NotifyEmail, @NotifyName;
        END

        CLOSE notify_cursor;
        DEALLOCATE notify_cursor;

        -- =================================================================
        -- A7. RETURN RESULT
        -- =================================================================
        SELECT
            'SUCCESS' AS Status,
            @ProcessedCount AS InsertedPendingCount,
            @CountRequired AS ApprovalsRequired,
            @EffectivePermissionId AS EffectivePermissionId,
            @EffectiveBranchId AS EffectiveBranchId,
            @EffectiveSpecificUserIds AS EffectiveSpecificUserIds,
            @EligibleCount AS EligibleUserCount,
            @ErrorCount AS NotificationErrors;
        RETURN;
    END

    -- =====================================================================
    -- MODE B: BATCH PROCESSING (no specific record -- process all unassigned)
    -- =====================================================================
    BEGIN TRY

        IF OBJECT_ID('tempdb..#WorkSet') IS NOT NULL DROP TABLE #WorkSet;
        CREATE TABLE #WorkSet (
            RowNum INT IDENTITY(1,1) PRIMARY KEY,
            HistoryId BIGINT,
            Source NVARCHAR(255),
            SourceID NVARCHAR(100),
            StageId BIGINT,
            PermissionId BIGINT,
            WorkflowType NVARCHAR(20),
            CountRequired INT,
            MakerId BIGINT,
            StageName NVARCHAR(255),
            ItemLabel NVARCHAR(255),
            SpecificUserIds NVARCHAR(MAX),
            BranchId BIGINT,
            OverridePermissionId BIGINT
        );

        -- Get latest unprocessed history entry per Source+SourceID (deduplicated)
        ;WITH LatestPending AS (
            SELECT
                h.Id, h.Source, h.SourceID, h.Stage, h.CreatedBy,
                ROW_NUMBER() OVER (PARTITION BY h.Source, h.SourceID ORDER BY h.CreatedOn DESC) AS rn
            FROM t_WorkFlowHistory h WITH (NOLOCK)
            WHERE h.DeletedOn IS NULL
              AND h.isApproved IS NULL
              AND h.StatusId = @SubmittedStatusId
        )
        INSERT INTO #WorkSet (HistoryId, Source, SourceID, StageId, PermissionId, WorkflowType, CountRequired, MakerId, StageName, ItemLabel, SpecificUserIds, BranchId, OverridePermissionId)
        SELECT
            lp.Id, lp.Source, lp.SourceID, CAST(lp.Stage AS BIGINT),
            s.PermissionId, wt.TypeId, ISNULL(s.[Count], 1), lp.CreatedBy, s.StageName,
            CASE
                WHEN NULLIF(LTRIM(RTRIM(w.Name)), '') IS NULL
                  OR LTRIM(RTRIM(w.Name)) = lp.Source
                  OR LTRIM(RTRIM(w.Name)) LIKE 't[_]%'
                THEN 'workflow item'
                ELSE LTRIM(RTRIM(w.Name))
            END,
            NULL, NULL, NULL
        FROM LatestPending lp
        JOIN t_WorkFlowStages s WITH (NOLOCK) ON s.Id = CAST(lp.Stage AS BIGINT) AND s.DeletedOn IS NULL
        LEFT JOIN t_WorkFlowTypes wt WITH (NOLOCK) ON s.WorkFlowTypeId = wt.Id
        LEFT JOIN t_WorkFlows w WITH (NOLOCK) ON s.WorkFlowId = w.Id AND w.DeletedOn IS NULL
        WHERE lp.rn = 1
          AND NOT EXISTS (
              SELECT 1 FROM t_WorkFlowPending p WITH (NOLOCK)
              WHERE p.Source = lp.Source
                AND p.SourceID = lp.SourceID
                AND p.Stage = lp.Stage
                AND p.DeletedOn IS NULL
          );

        DECLARE @BatchTotal INT = (SELECT COUNT(*) FROM #WorkSet);

        IF @BatchTotal = 0
        BEGIN
            SELECT 'SUCCESS' AS Status, 0 AS InsertedPendingCount, 0 AS Errors, 'No pending items to process' AS Message;
            DROP TABLE IF EXISTS #WorkSet;
            RETURN;
        END

        -- Process each work item
        DECLARE
            @BatchRowNum INT = 1,
            @BatchMaxRow INT = @BatchTotal,
            @BatchHistoryId BIGINT,
            @BatchSource NVARCHAR(255),
            @BatchSourceID NVARCHAR(100),
            @BatchStageId BIGINT,
            @BatchPermId BIGINT,
            @BatchWFType NVARCHAR(20),
            @BatchCountReq INT,
            @BatchMaker BIGINT,
            @BatchStageName NVARCHAR(255),
            @BatchItemLabel NVARCHAR(255),
            @BatchStagePermName NVARCHAR(255),
            @BatchStagePermId BIGINT,
            @BatchEffPerm BIGINT,
            @BatchSpecificUserIds NVARCHAR(MAX),
            @BatchBranchId BIGINT,
            @BatchOverridePermissionId BIGINT,
            @BatchEligCount INT;

        WHILE @BatchRowNum <= @BatchMaxRow
        BEGIN
            SELECT
                @BatchHistoryId = HistoryId,
                @BatchSource = Source,
                @BatchSourceID = SourceID,
                @BatchStageId = StageId,
                @BatchPermId = PermissionId,
                @BatchWFType = WorkflowType,
                @BatchCountReq = CountRequired,
                @BatchMaker = MakerId,
                @BatchStageName = StageName,
                @BatchItemLabel = ItemLabel,
                @BatchSpecificUserIds = SpecificUserIds,
                @BatchBranchId = BranchId,
                @BatchOverridePermissionId = OverridePermissionId
            FROM #WorkSet
            WHERE RowNum = @BatchRowNum;

            BEGIN TRY
                SET @BatchStagePermName = 'workflowstage_' + REPLACE(ISNULL(@BatchStageName, ''), ' ', '');
                SET @BatchStagePermId = NULL;

                SELECT TOP 1 @BatchStagePermId = p.Id
                FROM t_Permissions p WITH (NOLOCK)
                WHERE p.Name = @BatchStagePermName;

                -- Prefer stage-scoped permission when present; fall back to configured stage PermissionId.
                SET @BatchEffPerm = ISNULL(@BatchStagePermId, @BatchPermId);
                SET @BatchEffPerm = COALESCE(@BatchOverridePermissionId, @BatchEffPerm);

                -- Validate resolved permission exists
                IF @BatchEffPerm IS NULL
                BEGIN
                    SET @ErrorCount += 1;
                    SET @BatchRowNum += 1;
                    CONTINUE;
                END

                -- For AMT types in batch, we can't determine amount without dynamic SQL
                -- so we use the default stage permission. For amount-aware processing,
                -- callers should use Mode A (specific record) with @Amount parameter.

                -- Get eligible users for this batch item
                DELETE FROM @BatchEligible;

                INSERT INTO @BatchEligible (Id, Name, Email)
                SELECT DISTINCT u.Id, u.Name, u.Email
                FROM t_Users u WITH (NOLOCK)
                WHERE u.DeletedOn IS NULL
                  AND u.Id <> ISNULL(@BatchMaker, 0)
                  AND u.UserID <> 'CSADM'
                  AND EXISTS (
                      SELECT 1 FROM dbo.f_getUserWithPermission(@BatchEffPerm) perm
                      WHERE perm.Id = u.Id
                  )
                  AND (
                      @BatchSpecificUserIds IS NULL
                      OR EXISTS (
                          SELECT 1
                          FROM STRING_SPLIT(
                              REPLACE(REPLACE(REPLACE(@BatchSpecificUserIds, '[', ''), ']', ''), '"', ''),
                              ','
                          ) ids
                          WHERE TRY_CAST(LTRIM(RTRIM(ids.value)) AS BIGINT) = u.Id
                      )
                  )
                  AND (
                      @BatchBranchId IS NULL
                      OR TRY_CAST(u.BranchId AS BIGINT) = @BatchBranchId
                      OR EXISTS (
                          SELECT 1
                          FROM t_ModelRoles mr WITH (NOLOCK)
                          INNER JOIN t_RolePermissions rp WITH (NOLOCK) ON mr.role_id = rp.role_id
                          WHERE TRY_CAST(mr.model_id AS BIGINT) = u.Id
                            AND mr.model_type = 'UserID'
                            AND mr.BranchId = @BatchBranchId
                            AND rp.permission_id = @BatchEffPerm
                            AND mr.DeletedOn IS NULL
                      )
                  )
                  AND NOT EXISTS (
                      SELECT 1 FROM dbo.t_WorkFlowHistory h WITH (NOLOCK)
                      WHERE h.Source = @BatchSource
                        AND h.SourceID = @BatchSourceID
                        AND h.Stage = CAST(@BatchStageId AS NVARCHAR(200))
                        AND h.CreatedBy = u.Id
                        AND h.StatusId IN (@ApprovedStatusId, @RejectedStatusId)
                        AND h.DeletedOn IS NULL
                  );

                SET @BatchEligCount = (SELECT COUNT(*) FROM @BatchEligible);

                -- For batch items, if stage-scoped permission has no users, retry configured stage permission.
                IF @BatchEligCount = 0
                   AND @BatchPermId IS NOT NULL
                   AND @BatchPermId <> @BatchEffPerm
                BEGIN
                    SET @BatchEffPerm = @BatchPermId;
                    DELETE FROM @BatchEligible;

                    INSERT INTO @BatchEligible (Id, Name, Email)
                    SELECT DISTINCT u.Id, u.Name, u.Email
                    FROM t_Users u WITH (NOLOCK)
                    WHERE u.DeletedOn IS NULL
                      AND u.Id <> ISNULL(@BatchMaker, 0)
                      AND u.UserID <> 'CSADM'
                      AND EXISTS (
                          SELECT 1 FROM dbo.f_getUserWithPermission(@BatchEffPerm) perm
                          WHERE perm.Id = u.Id
                      )
                      AND (
                          @BatchSpecificUserIds IS NULL
                          OR EXISTS (
                              SELECT 1
                              FROM STRING_SPLIT(
                                  REPLACE(REPLACE(REPLACE(@BatchSpecificUserIds, '[', ''), ']', ''), '"', ''),
                                  ','
                              ) ids
                              WHERE TRY_CAST(LTRIM(RTRIM(ids.value)) AS BIGINT) = u.Id
                          )
                      )
                      AND (
                          @BatchBranchId IS NULL
                          OR TRY_CAST(u.BranchId AS BIGINT) = @BatchBranchId
                          OR EXISTS (
                              SELECT 1
                              FROM t_ModelRoles mr WITH (NOLOCK)
                              INNER JOIN t_RolePermissions rp WITH (NOLOCK) ON mr.role_id = rp.role_id
                              WHERE TRY_CAST(mr.model_id AS BIGINT) = u.Id
                                AND mr.model_type = 'UserID'
                                AND mr.BranchId = @BatchBranchId
                                AND rp.permission_id = @BatchEffPerm
                                AND mr.DeletedOn IS NULL
                          )
                      )
                      AND NOT EXISTS (
                          SELECT 1 FROM dbo.t_WorkFlowHistory h WITH (NOLOCK)
                          WHERE h.Source = @BatchSource
                            AND h.SourceID = @BatchSourceID
                            AND h.Stage = CAST(@BatchStageId AS NVARCHAR(200))
                            AND h.CreatedBy = u.Id
                            AND h.StatusId IN (@ApprovedStatusId, @RejectedStatusId)
                            AND h.DeletedOn IS NULL
                      );

                    SET @BatchEligCount = (SELECT COUNT(*) FROM @BatchEligible);
                END

                IF @BatchEligCount = 0
                BEGIN
                    SET @ErrorCount += 1;
                    SET @BatchRowNum += 1;
                    CONTINUE;
                END

                -- Transactional write for this batch item
                BEGIN TRANSACTION;

                -- Soft-delete existing pending
                UPDATE dbo.t_WorkFlowPending
                SET DeletedBy = @SystemUserId,
                    DeletedOn = @Now,
                    ModifiedBy = @SystemUserId,
                    ModifiedOn = @Now
                WHERE Source = @BatchSource
                  AND SourceID = @BatchSourceID
                  AND Stage = CAST(@BatchStageId AS NVARCHAR(200))
                  AND DeletedOn IS NULL;

                -- Insert new pending records
                INSERT INTO t_WorkFlowPending (
                    Source, SourceID, Stage, UserId,
                    CreatedBy, CreatedOn, ModifiedBy, ModifiedOn
                )
                SELECT
                    @BatchSource,
                    @BatchSourceID,
                    CAST(@BatchStageId AS NVARCHAR(200)),
                    e.Id,
                    @SystemUserId,
                    @Now,
                    @SystemUserId,
                    @Now
                FROM @BatchEligible e;

                SET @ProcessedCount += @@ROWCOUNT;

                -- Mark history as processed
                UPDATE t_WorkFlowHistory
                SET isApproved = 0,
                    ModifiedBy = @SystemUserId,
                    ModifiedOn = @Now
                WHERE Id = @BatchHistoryId
                  AND isApproved IS NULL;

                COMMIT TRANSACTION;

                -- Send notifications (outside transaction, per batch item)
                DECLARE batch_notify_cursor CURSOR LOCAL FAST_FORWARD FOR
                    SELECT Id, Email, Name FROM @BatchEligible;

                OPEN batch_notify_cursor;
                FETCH NEXT FROM batch_notify_cursor INTO @BNotifyId, @BNotifyEmail, @BNotifyName;

                WHILE @@FETCH_STATUS = 0
                BEGIN
                    BEGIN TRY
                        IF @BNotifyEmail IS NOT NULL AND LEN(@BNotifyEmail) > 5
                        BEGIN
                            SET @BSubject = 'Approval Required: ' + @BatchItemLabel;
                            SET @BMessage =
                                'Dear ' + ISNULL(@BNotifyName, 'User') + ',' + CHAR(13) + CHAR(10) +
                                CHAR(13) + CHAR(10) +
                                'An Item in the ' + @BatchItemLabel + ' requires your approval.' + CHAR(13) + CHAR(10) +
                                'Please log in to review and take action.';

                            EXEC p_sendNotificationEmail
                                @UserID = @BNotifyId,
                                @Subject = @BSubject,
                                @Message = @BMessage,
                                @SenderId = @SystemUserId,
                                @Source = @BatchSource,
                                @SourceID = @BatchSourceID;
                        END
                    END TRY
                    BEGIN CATCH
                        -- Email failure should not fail the batch
                    END CATCH

                    FETCH NEXT FROM batch_notify_cursor INTO @BNotifyId, @BNotifyEmail, @BNotifyName;
                END

                CLOSE batch_notify_cursor;
                DEALLOCATE batch_notify_cursor;

            END TRY
            BEGIN CATCH
                IF @@TRANCOUNT > 0
                    ROLLBACK TRANSACTION;

                SET @ErrorCount += 1;
            END CATCH

            SET @BatchRowNum += 1;
        END

        DROP TABLE IF EXISTS #WorkSet;

        SELECT
            'SUCCESS' AS Status,
            @ProcessedCount AS InsertedPendingCount,
            @BatchTotal AS TotalItemsFound,
            @ErrorCount AS Errors;

    END TRY
    BEGIN CATCH
        IF @@TRANCOUNT > 0
            ROLLBACK TRANSACTION;

        DROP TABLE IF EXISTS #WorkSet;

        DECLARE @BatchError NVARCHAR(4000) = ERROR_MESSAGE();
        RAISERROR(@BatchError, 16, 1);
    END CATCH
END
GO
