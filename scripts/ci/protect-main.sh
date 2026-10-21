#!/usr/bin/env bash
# Jadikan job CI "php" syarat wajib gabung ke main. Jalankan SETELAH CI lulus
# minimal sekali di GitHub (konteks status belum ada sebelum run pertama).
# Perlu: gh login dengan hak admin repo. Pakai DRY_RUN=1 untuk melihat payload.
set -euo pipefail

REPO="${REPO:-$(gh repo view --json nameWithOwner -q .nameWithOwner)}"
BRANCH="${BRANCH:-main}"
payload='{
  "required_status_checks": {"strict": true, "contexts": ["php"]},
  "enforce_admins": false,
  "required_pull_request_reviews": {"required_approving_review_count": 0},
  "restrictions": null,
  "allow_force_pushes": false,
  "allow_deletions": false
}'

if [ "${DRY_RUN:-0}" = "1" ]; then
  echo "PUT repos/${REPO}/branches/${BRANCH}/protection"
  echo "$payload"
  exit 0
fi

echo "$payload" | gh api -X PUT "repos/${REPO}/branches/${BRANCH}/protection" --input - >/dev/null
echo "Branch ${BRANCH} di ${REPO}: CI 'php' kini wajib lulus sebelum gabung."
