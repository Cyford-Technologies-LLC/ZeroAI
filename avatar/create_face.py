import numpy as np
from PIL import Image, ImageDraw

# Create a simple face image
img = Image.new('RGB', (512, 512), color=(240, 240, 240))
draw = ImageDraw.Draw(img)

# Draw face
draw.ellipse([100, 100, 412, 412], fill=(200, 180, 160))
# Eyes
draw.ellipse([180, 200, 210, 230], fill=(0, 0, 0))
draw.ellipse([302, 200, 332, 230], fill=(0, 0, 0))
# Mouth
draw.ellipse([226, 280, 286, 310], fill=(100, 50, 50))

img.save('default_face.jpg')
print("Default face created")