#!/usr/bin/env bash
# NFR-CICD-001: preview is offline; mutation needs explicit approval and an independent reviewer.
set -euo pipefail
REPO="${REPO:-hanifp62/online-shop}"
BRANCH="${BRANCH:-main}"
DRY_RUN="${DRY_RUN:-1}"
[[ "$REPO" =~ ^[A-Za-z0-9_-]+/[A-Za-z0-9_.-]+$ && "$BRANCH" = main ]] || {
  echo 'Target repo/main tidak valid.' >&2; exit 2;
}
payload='{
  "required_status_checks": {"strict": true, "checks": [{"context": "php", "app_id": 15368}]},
  "enforce_admins": true,
  "required_pull_request_reviews": {"required_approving_review_count": 1, "dismiss_stale_reviews": true, "require_last_push_approval": true},
  "restrictions": null,
  "allow_force_pushes": false,
  "allow_deletions": false
}'
if [ "$DRY_RUN" = 1 ]; then
  printf 'DRY RUN: PUT repos/%s/branches/%s/protection\n%s\n' "$REPO" "$BRANCH" "$payload"
  exit 0
fi
[[ "$DRY_RUN" = 0 && "${APPLY:-0}" = 1 && "${REVIEWER_LOGIN:-}" =~ ^[A-Za-z0-9-]+$ ]] || {
  echo 'Mutasi memerlukan DRY_RUN=0 APPLY=1 dan REVIEWER_LOGIN independen.' >&2; exit 2;
}
actor_id=$(gh api user --jq .id)
reviewer_record=$(gh api "repos/$REPO/collaborators/$REVIEWER_LOGIN/permission" --jq '[.user.id, .permission] | @tsv')
read -r reviewer_id reviewer_role <<< "$reviewer_record"
[[ "$actor_id" =~ ^[1-9][0-9]*$ && "$reviewer_id" =~ ^[1-9][0-9]*$ ]] || {
  echo 'Identitas akun tidak dapat diverifikasi; proteksi tidak diubah.' >&2; exit 2;
}
[ "$reviewer_id" != "$actor_id" ] || { echo 'Reviewer tidak boleh akun pelaksana.' >&2; exit 2; }
case "$reviewer_role" in
  admin|maintain|write) ;;
  *) echo 'Reviewer perlu hak review/write pada repo; proteksi tidak diubah.' >&2; exit 2 ;;
esac
protected=$(gh api "repos/$REPO/branches/$BRANCH" --jq .protected)
[ "$protected" = false ] || { echo 'Aturan existing perlu review manual; tidak ditimpa.' >&2; exit 2; }
check_count=$(gh api "repos/$REPO/commits/$BRANCH/check-runs" --jq '[.check_runs[] | select(.name == "php" and .app.id == 15368 and .conclusion == "success")] | length')
[[ "$check_count" =~ ^[0-9]+$ && "$check_count" -gt 0 ]] || {
  echo 'Belum ada check php sukses dari GitHub Actions pada head main.' >&2; exit 2;
}
printf '%s' "$payload" | gh api -X PUT "repos/$REPO/branches/$BRANCH/protection" --input - >/dev/null
verified=$(gh api "repos/$REPO/branches/$BRANCH/protection" --jq '
  .required_status_checks.strict == true and
  any(.required_status_checks.checks[]; .context == "php" and .app_id == 15368) and
  .enforce_admins.enabled == true and
  .required_pull_request_reviews.required_approving_review_count >= 1 and
  .required_pull_request_reviews.dismiss_stale_reviews == true and
  .required_pull_request_reviews.require_last_push_approval == true and
  .allow_force_pushes.enabled == false and .allow_deletions.enabled == false')
[ "$verified" = true ] || { echo 'Verifikasi proteksi gagal; maintainer harus memeriksa remote.' >&2; exit 1; }
echo 'Proteksi main terverifikasi: php wajib dari GitHub Actions dan approval independen.'
