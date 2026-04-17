# SortByReference Benchmark Suite (PHP)

This project benchmarks multiple strategies for sorting an array by a reference order (`$order`) in PHP.

It explores several algorithmic families:
- Comparison-based sorting (`usort`, `uasort`, `multisort`)
- Decorate-sort-undecorate (Schwartzian transform)
- Rank projection sorting (`array_flip` + scalar sort)
- Partition-based sorting (partial sort optimization)
- Bucket / counting-style sorting (near-linear approaches)

---

# ✅ Correctness Status

All implementations are now **functionally equivalent**:

✔ All algorithms produce identical results  
✔ Bucket implementation corrected to use **rank-based grouping**  
✔ Stable behavior preserved across all methods  

---

# 📊 Benchmark Summary (Strings)

## 🔹 20 elements

| Method | Time | Memory |
|--------|------|--------|
| bucket | ⭐ fastest (~2.0 µs) | moderate |
| rank_array | very close | low |
| multisort | similar | higher |
| partition | slower | moderate |
| hash / schwartz | slowest | high |

---

## 🔹 200 elements

| Method | Time | Memory |
|--------|------|--------|
| bucket | ⭐ fastest |
| rank_array | solid second |
| multisort | close third |
| partition | slower |
| schwartz / hash | slowest |

---

## 🔹 2,000 elements

| Method | Time | Memory |
|--------|------|--------|
| bucket | ⭐ dominant |
| rank_array | ~2x slower |
| multisort | similar to rank |
| partition | much slower |
| schwartz | heavy memory |
| hash | slowest |

---

## 🔹 20,000 elements

| Method | Time | Memory |
|--------|------|--------|
| bucket | ⭐ fastest (~1.8 ms) |
| rank_array | ~2.4x slower |
| multisort | ~3x slower |
| partition | ~7x slower |
| schwartz | ~10x slower + high memory |
| hash | slowest |

---

## 🔹 200,000 elements

| Method | Time | Memory |
|--------|------|--------|
| bucket | ⭐ best (~30 ms) | high |
| rank_array | ~2x slower | lower |
| multisort | ~3x slower | high |
| partition | ~7x slower | moderate |
| schwartz | ~10x slower | 🚨 very high memory |
| hash | slowest |

---

# 🧠 Algorithm Families

## 1. Bucket / Linear (O(n + k)) — 🏆 Best Performer

### Includes:
- `sortByReference_bucket`

### Pattern:
```

value → rank → bucket → linear flatten

```id="pattern_bucket"

### Characteristics:
- No comparisons
- Near-linear time
- Excellent scaling
- Stable ordering
- Requires rank mapping

### Tradeoffs:
- Higher memory usage (buckets)
- Best when `$order` is moderate size

---

## 2. Rank Projection (O(n log n))

### Includes:
- `sortByReference_rank_array`

### Pattern:
```

value → rank → sort ranks → rebuild

```id="pattern_rank"

### Characteristics:
- No comparator overhead
- Simpler than bucket approach
- Lower memory than bucket
- Reliable fallback

---

## 3. Multisort (O(n log n))

### Includes:
- `sortByReference_multisort`

### Characteristics:
- Native C-level sorting
- Competitive performance
- Higher memory overhead

---

## 4. Partition Sort (O(n log m))

### Includes:
- `sortByReference_partition`

### Pattern:
```

split → sort subset → merge

```id="pattern_partition"

### Characteristics:
- Only sorts ranked subset
- Good when few items match `$order`
- Degrades if most items match

---

## 5. Comparison-based (O(n log n)) — ❌ Slowest

### Includes:
- `sortByReference_hash`
- `sortByReference_schwartz`

### Characteristics:
- Flexible
- High comparator overhead
- Poor scaling

---

# 🚀 Key Insights

## 🥇 Bucket sort dominates
- Consistently fastest across all sizes
- Performance gap widens as dataset grows
- True near-linear scaling

---

## 🥈 Rank projection is best fallback
- Lower memory than bucket
- Predictable performance
- Good default for mid-sized datasets

---

## 🥉 Multisort is a strong general-purpose option
- Competitive with rank_array
- Benefits from internal optimizations

---

## ❌ Comparator-based approaches do not scale
- `usort`, `uasort`, Schwartz degrade rapidly
- Function call overhead dominates runtime

---

# ⚙️ Recommended Strategy

## 🔹 Use `bucket` when:
- Large datasets (1k+ elements)
- Performance is critical
- `$order` size is reasonable
- Memory is not constrained

---

## 🔹 Use `rank_array` when:
- Moderate dataset size
- Lower memory usage desired
- Simplicity matters

---

## 🔹 Use `multisort` when:
- You want native PHP performance
- Accept higher memory usage

---

## 🔹 Use `partition` when:
- Only a small subset of values is ranked
- Many elements fall outside `$order`

---

## 🔹 Avoid when possible:
- `hash`
- `uasort`
- `schwartz`

---

# 🧠 Final Takeaway

Sorting performance improves dramatically when moving from:

```

comparison-based → rank-based → bucket-based

```id="progression"

The optimal strategy is:

> 👉 **Eliminate comparisons and place elements directly**

Bucket-based sorting achieves this and represents the practical performance ceiling in PHP for this problem.

---

# 🔮 Future Improvements

Potential next steps:

- Adaptive strategy selection (auto-switch based on input size/distribution)
- Radix-style extensions for structured string keys
- Memory-optimized bucket variants for very large datasets
