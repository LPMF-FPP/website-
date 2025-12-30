#!/bin/bash
# Script to update WALKTHROUGH.md
# Usage: ./scripts/update-walkthrough.sh "Section Name" "Content"

WALKTHROUGH="/home/lpmf-dev/website-/WALKTHROUGH.md"
SECTION=$1
CONTENT=$2
DATE=$(date '+%Y-%m-%d %H:%M:%S')

if [ -z "$SECTION" ] || [ -z "$CONTENT" ]; then
    echo "Usage: ./scripts/update-walkthrough.sh \"Section Name\" \"Content\""
    echo "Example: ./scripts/update-walkthrough.sh \"Fix/Login Bug\" \"Fixed login timeout issue\""
    exit 1
fi

# Append new section at the end of WALKTHROUGH.md
cat >> "$WALKTHROUGH" << SECTION_EOF

<a id="$(echo "$SECTION" | tr ' ' '-' | tr '[:upper:]' '[:lower:]')"></a>

## 📝 $SECTION

\`\`\`
Updated: $DATE
\`\`\`

$CONTENT

---

SECTION_EOF

echo "✅ Added section '$SECTION' to WALKTHROUGH.md"
