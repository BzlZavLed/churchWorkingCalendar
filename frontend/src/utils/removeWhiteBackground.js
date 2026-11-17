// Only remove near-white pixels reachable from an image edge. Enclosed whites stay intact.
export function removeEdgeWhite(data, width, height) {
  const count = width * height
  const seen = new Uint8Array(count)
  const queue = new Uint32Array(count)
  let head = 0, tail = 0, removed = 0
  const visit = (pixel) => {
    if (seen[pixel]) return
    seen[pixel] = 1
    const i = pixel * 4
    if (data[i + 3] === 0 || (Math.min(data[i], data[i + 1], data[i + 2]) >= 240 && Math.max(data[i], data[i + 1], data[i + 2]) - Math.min(data[i], data[i + 1], data[i + 2]) <= 12)) {
      queue[tail++] = pixel
    }
  }
  for (let x = 0; x < width; x++) { visit(x); visit((height - 1) * width + x) }
  for (let y = 0; y < height; y++) { visit(y * width); visit(y * width + width - 1) }
  while (head < tail) {
    const pixel = queue[head++], x = pixel % width, i = pixel * 4
    if (data[i + 3]) removed++
    data[i + 3] = 0
    if (x > 0) visit(pixel - 1)
    if (x < width - 1) visit(pixel + 1)
    if (pixel >= width) visit(pixel - width)
    if (pixel < count - width) visit(pixel + width)
  }
  return removed
}
