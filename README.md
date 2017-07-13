# tvarchive-ai_suite
A suite of tools for exploring AI research against video


## Here is a script to quickly reaname a folder of images

```shell
counter=1
for f in *.jpg; do
    a="positive_$counter.jpg"
    mv "$f" "$a"
    counter=$((counter + 1))
done
```