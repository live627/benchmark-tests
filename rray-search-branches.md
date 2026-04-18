Nice—this is where things get *really* interesting, because your benchmark can expose CPU-level behavior, not just PHP-level differences.

Branch prediction mainly affects this kind of code:

```php
if ($v === $needle) {
    return $i;
}
```

The CPU tries to guess whether that `if` will be true or false. When it guesses wrong, you pay a **pipeline flush penalty** (~10–20 cycles on modern CPUs). Over millions of iterations, that adds up.

---

## 🧪 What we want to test

We’ll create **different predictability patterns**:

| Pattern       | Behavior                | CPU prediction |
| ------------- | ----------------------- | -------------- |
| always miss   | condition always false  | ✅ perfect      |
| hit at end    | false…false…true (once) | ✅ very good    |
| random hit    | unpredictable           | ❌ worst        |
| frequent hits | alternating true/false  | ❌ bad          |


```
=== Branch Prediction Benchmark (2,000) ===
--------------------------------------------------------------------------------
Pattern                          | Median Time   | Avg Time
--------------------------------------------------------------------------------
predictable_miss                 |     44.700 µs |     44.600 µs
predictable_end                  |     46.900 µs |     46.590 µs
alternating                      |     61.300 µs |     61.270 µs
unpredictable                    |    176.900 µs |    184.180 µs
--------------------------------------------------------------------------------

=== Branch Prediction Benchmark (20,000) ===
--------------------------------------------------------------------------------
Pattern                          | Median Time   | Avg Time
--------------------------------------------------------------------------------
predictable_miss                 |    402.900 µs |    394.380 µs
predictable_end                  |    446.500 µs |    449.240 µs
alternating                      |    550.800 µs |    553.090 µs
unpredictable                    |   1637.300 µs |   1639.010 µs
--------------------------------------------------------------------------------

=== Branch Prediction Benchmark (200,000) ===
--------------------------------------------------------------------------------
Pattern                          | Median Time   | Avg Time
--------------------------------------------------------------------------------
predictable_miss                 |   3789.400 µs |   3829.730 µs
predictable_end                  |   3991.200 µs |   4057.740 µs
alternating                      |   5536.100 µs |   5558.690 µs
unpredictable                    |  16555.900 µs |  16637.350 µs
--------------------------------------------------------------------------------
```

---

## 🧠 What you should expect (this is the fun part)

### 🥇 Fastest: predictable miss

* Branch always false → CPU learns instantly
* Almost zero misprediction penalty

### 🥈 predictable end

* Only **1 misprediction total**
* Still extremely efficient

### 🥉 alternating

* CPU struggles (pattern harder to learn)
* noticeable slowdown

### 💀 worst: unpredictable

* random branches → constant misprediction
* can be **2–3× slower** than predictable case

---

## 🔥 Key insight (this surprises people)

Two loops that are both **O(n)** can differ by **multiple factors** purely due to branch prediction.

So this:

```php
foreach ($a as $v) {
    if ($v === $needle) return true;
}
```

is not “just O(n)” — it has **data-dependent CPU behavior**.

---

## ⚠️ Important caveat in PHP

PHP adds noise:

* interpreter overhead
* hash table iteration
* function call cost

So the effect will be **visible but muted** compared to C.

Still measurable at large `n` (like 200k+).
