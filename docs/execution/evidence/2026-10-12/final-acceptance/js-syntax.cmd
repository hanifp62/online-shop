sh -c "find resources/js public/prototype -name '*.js' -print0 | xargs -0 -n1 node --check"
