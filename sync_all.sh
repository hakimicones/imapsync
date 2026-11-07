#!/bin/bash
while IFS=";" read -r u1 p1 u2 p2; do
    imapsync \
    --host1 mail.gema-group.com --user1 "$u1" --password1 "$p1" \
    --host2 mail2.gema-group.com --user2 "$u2" --password2 "$p2"
done < accounts.txt
