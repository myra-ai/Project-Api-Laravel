#!/bin/bash

git add -A
git commit -m "Revision: $(crc32 <(echo $(date)))"
git push -u bitbucket main
git push -u github main
