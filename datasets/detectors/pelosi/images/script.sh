counter=1
for f in *.jpg; do
    a="positive_$counter.jpg"
    mv "$f" "$a"
    counter=$((counter + 1))
done
