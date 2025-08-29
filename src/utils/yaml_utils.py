import yaml
import logging

logger = logging.getLogger(__name__)


def load_yaml_config(file_path: str) -> dict:
    """
    Safely loads a YAML file and returns its content as a dictionary.

    Args:
        file_path (str): The path to the YAML file.

    Returns:
        dict: The parsed YAML content.

    Raises:
        FileNotFoundError: If the file does not exist.
        yaml.YAMLError: If there is an error parsing the YAML.
    """
    try:
        with open(file_path, 'r') as file:
            return yaml.safe_load(file)
    except FileNotFoundError:
        logger.error(f"Configuration file not found: {file_path}")
        raise
    except yaml.YAMLError as e:
        logger.error(f"Error parsing YAML file {file_path}: {e}")
        raise
