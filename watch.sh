#!/usr/bin/env bash

function upd {
    date
    cp -R src/* htdocs/magento/magento17/
    cp -R src/* htdocs/magento/magento18/
    cp -R src/* htdocs/magento/magento19/
    echo "done"
}

export -f upd

bash -c "upd"

fswatch -o src/ | xargs -n1 -I{} bash -c "upd"
