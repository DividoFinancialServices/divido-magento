#!/usr/bin/env bash

function upd {
    date
    rsync -a src/ htdocs/magento/magento17
    rsync -a src/ htdocs/magento/magento18
    rsync -a src/ htdocs/magento/magento19
    echo "done"
}

export -f upd

bash -c "upd"

fswatch -o src/ | xargs -n1 -I{} bash -c "upd"
