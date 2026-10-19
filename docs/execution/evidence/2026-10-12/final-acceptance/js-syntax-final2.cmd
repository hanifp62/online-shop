sh -c "find resources/js public/prototype public/js -name '*.js' -print0 | xargs -0 -n1 node --check"
