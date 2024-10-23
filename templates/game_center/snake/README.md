# Snake

Source: <https://github.com/patorjk/JavaScript-Snake>

``` shell name=build
git clone https://github.com/patorjk/JavaScript-Snake tmp
rm -fr tmp/.* tmp/README.md
rsync -azv tmp/* .
rm -fr tmp
cat index.html | sed 's@="\./@="@' > index.html.twig
```
