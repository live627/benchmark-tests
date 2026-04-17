# SortByReference Benchmark Suite (PHP)

This project benchmarks multiple strategies for sorting an array by a reference order (`$order`) in PHP.

It explores several algorithmic families:
- Comparison-based sorting (`usort`, `uasort`, `multisort`)
- Decorate-sort-undecorate (Schwartzian transform)
- Rank projection sorting (`array_flip` + comparator)
- Partition-based sorting (partial sort optimization)
- Bucket / counting-style sorting (near-linear approaches)
- Hybrid adaptive strategies

---

# 📊 Benchmark Summary

Results below show median performance across different dataset sizes.

## 🔹 20 elements

| Method | Time | Memory |
|--------|------|--------|
| linear (bucket-style) | ⭐ fastest (1.9 µs) | moderate |
| asort | close second | low |
| bucket / hybrid | competitive | moderate |
| partition | slower | lower |
| hash / uasort / schwartz | slowest group | highest memory |

---

## 🔹 200 elements

| Method | Time | Memory |
|--------|------|--------|
| linear | ⭐ fastest |
| hybrid / bucket | close second |
| asort / rank_array | mid-tier |
| partition | slow |
| hash / uasort / schwartz | slowest |

---

## 🔹 2,000 elements

| Method | Time | Memory |
|--------|------|--------|
| linear | ⭐ best overall |
| hybrid | strong second |
| bucket | close competitor |
| asort / rank_array | mid-tier |
| partition | slower but stable memory |
| hash / uasort | slow |
| schwartz | very high memory cost |

---

## 🔹 20,000 elements

| Method | Time | Memory |
|--------|------|--------|
| linear | ⭐ fastest overall |
| bucket / hybrid | strong scaling |
| asort / rank_array | mid-tier |
| partition | slower but memory efficient |
| multisort | high memory |
| hash / uasort | slowest group |
| schwartz | highest memory usage |

---

# ⚠️ Correctness Note

Across all benchmarks:

```

❌ MISMATCH: sortbyreference_bucket differs from sortbyreference_hash

```

This indicates:
- `bucket` implementation does NOT match comparator-based reference behavior
- Likely causes:
  - missing ordering guarantees for non-ranked values
  - differences in stability or grouping logic

👉 Fix required before production use if strict equivalence is needed.

---

# 🧠 Algorithm Families

## 1. Comparison-based (O(n log n))

### Includes:
- `sortByReference_hash`
- `sortByReference_uasort`
- `sortByReference_multisort`
- `sortByReference_schwartz`

### Characteristics:
- Uses `usort` / `uasort`
- Flexible
- Slower at scale
- Higher CPU cost due to comparator calls

---

## 2. Rank Projection (Decorate-Sort)

### Includes:
- `sortByReference_rank_array`
- `sortByReference_asort`

### Pattern:
```

value → rank → sort ranks → rebuild

```

### Characteristics:
- Removes comparator overhead
- Still O(n log n)
- Simpler than full decoration approach

---

## 3. Partition-based sorting

### Includes:
- `sortByReference_partition`

### Pattern:
```

split → sort subset → merge

```

### Characteristics:
- Only sorts relevant subset
- Good when many elements are “unranked”
- Performance depends on distribution

---

## 4. Bucket / Counting-style (Near O(n))

### Includes:
- `sortByReference_bucket`
- `sortByReference_linear`
- `sortByReference_hybrid`

### Pattern:
```

rank/value → direct bucket → linear flatten

```

### Characteristics:
- No comparisons
- Near-linear scaling
- Best performance at large N
- Sensitive to correctness of grouping logic

---

# 🚀 Key Insights

## 🥇 Fastest Strategy (overall)
> Bucket / linear approaches dominate when correctly implemented

## 🥈 Best balanced strategy
> Hybrid / rank-based partitioning

## 🥉 Most stable but slow
> Hash / uasort / Schwartzian transform

---

# 🧪 Observations

### 1. Linear methods scale best
Performance advantage increases with dataset size.

### 2. Comparator-based methods degrade quickly
All `usort` variants fall behind at scale due to:
- repeated comparisons
- function call overhead

### 3. Memory tradeoffs matter
- Schwartzian transform = highest memory usage
- partition = most memory efficient comparison-based method

---

# ⚙️ Recommended Strategy Selection

## Use bucket / linear when:
- data can be grouped by rank
- near-linear performance needed
- large datasets (1k+)

## Use rank projection when:
- moderate dataset size
- simplicity matters
- stable ordering required

## Use partition when:
- only a subset is ordered
- most values are “unranked”

## Use comparison sorting when:
- flexibility matters more than performance
- dataset is small (<100 items)

---

# 🧠 Final Takeaway

This benchmark demonstrates a full progression of sorting strategies:

```

usort → rank sort → partition → bucket → linear grouping

```

Performance improves dramatically as the algorithm shifts from:
- comparisons ❌
- to precomputed ranks ✔
- to direct placement ✔✔
```
