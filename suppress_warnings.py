import warnings
import os

# Suppress transformers/tokenizers warnings about missing ML frameworks
os.environ['TOKENIZERS_PARALLELISM'] = 'false'
warnings.filterwarnings('ignore', message='None of PyTorch, TensorFlow >= 2.0, or Flax have been found')
warnings.filterwarnings('ignore', category=UserWarning, module='transformers')