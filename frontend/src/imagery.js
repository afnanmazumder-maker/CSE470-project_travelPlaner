export function cardImage(destination) {
  return destination.image_url || null
}

export function bannerImage(destination) {
  return destination.banner_url || destination.image_url || null
}

export function placeholderStyle(id) {
  const hue = (id * 57) % 360
  return {
    background: `linear-gradient(135deg, hsl(${hue} 60% 68%), hsl(${(hue + 40) % 360} 60% 50%))`,
  }
}