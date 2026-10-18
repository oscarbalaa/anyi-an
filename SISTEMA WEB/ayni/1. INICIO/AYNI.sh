#!/usr/bin/env bash
# AYNI - panel de control para Linux.  Uso: bash "1. INICIO/AYNI.sh"
exec bash "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/../scripts/ayni.sh" "$@"
