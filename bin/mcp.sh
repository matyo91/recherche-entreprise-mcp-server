#!/bin/bash

set -e
set -o pipefail

BASE=$(dirname $0)/..

date >> "$BASE/run.log"

stdin_log="$BASE/stdin.log"
stdout_log="$BASE/stdout.log"
stderr_log="$BASE/stderr.log"

tee -a "$stdin_log" | \
  $BASE/bin/console app:mcp > >(tee -a "$stdout_log") 2> >(tee -a "$stderr_log" >&2)