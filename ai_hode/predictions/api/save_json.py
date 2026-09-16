"""
AI-HODE JSON Exporter and Utility
Path: ai_hode/predictions/api/save_json.py

Provides robust helper functions for saving Python prediction models, 
telemetry output, and API responses directly to JSON files safely.
"""

import json
import os
from datetime import datetime
from typing import Dict, Any, Optional

EXPORTS_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "..", "..", "exports"))

def ensure_exports_dir() -> str:
    """Ensures the global exports directory exists."""
    if not os.path.exists(EXPORTS_DIR):
        os.makedirs(EXPORTS_DIR, exist_ok=True)
    return EXPORTS_DIR

def save_dict_to_json(data: Dict[str, Any], prefix: str = "ai_hode_prediction") -> Dict[str, Any]:
    """
    Safely serializes a Python dictionary to a formatted JSON file.
    
    :param data: Data dictionary to save
    :param prefix: File prefix for the saved artifact
    :return: Status dictionary with file details
    """
    try:
        target_dir = ensure_exports_dir()
        timestamp = datetime.now().strftime("%Y%m%d_%H%M%S")
        filename = f"{prefix}_{timestamp}.json"
        filepath = os.path.join(target_dir, filename)
        latest_filepath = os.path.join(target_dir, f"latest_{prefix}.json")

        payload = {
            "metadata": {
                "system": "AI-HODE Prediction Microservice",
                "saved_at": datetime.now().isoformat(),
                "prefix": prefix
            },
            "data": data
        }

        json_str = json.dumps(payload, indent=2, ensure_ascii=False, default=str)

        with open(filepath, "w", encoding="utf-8") as f:
            f.write(json_str)

        with open(latest_filepath, "w", encoding="utf-8") as f:
            f.write(json_str)

        return {
            "success": True,
            "message": "JSON saved successfully without error.",
            "filepath": filepath,
            "latest_filepath": latest_filepath,
            "filename": filename,
            "size_bytes": len(json_str.encode("utf-8"))
        }
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "message": f"Failed to save JSON: {str(e)}"
        }

if __name__ == "__main__":
    sample_prediction = {
        "department": "General OPD",
        "predicted_wait_minutes": 18.5,
        "queue_length": 4,
        "model_version": "v1.2-xgboost-ensemble"
    }
    result = save_dict_to_json(sample_prediction, prefix="sample_test")
    print(json.dumps(result, indent=2))
