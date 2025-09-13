#!/usr/bin/env bash
set -euo pipefail

PLUGIN_DIR="plugins/minpaku-channel-sync"
VERSION=$(php -r "
$h=file_get_contents('$PLUGIN_DIR/minpaku-channel-sync.php');
if(preg_match('/Version:\s*([0-9.]+)/',$h,$m)){echo $m[1];} else {echo '0.0.0';}
")

OUT="minpaku-channel-sync-$VERSION.zip"
echo "Packing $OUT ..."
cd "$(dirname "$0")/../.."
zip -r "$OUT" "plugins/minpaku-channel-sync" -x "*/.DS_Store" "*/node_modules/*" "*/vendor/*" "*.map" "*.log"
echo "Done: $OUT"
