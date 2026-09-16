"""
AI-HODE Prediction & Forecasting Engine
Path: ai_hode/training/predict.py
"""

import sys
import os
import time
import json
import warnings
warnings.filterwarnings("ignore")
import psycopg2
import pandas as pd
import numpy as np
from datetime import datetime
import joblib

DB_HOST = os.getenv("DB_HOST", "ep-blue-boat-auib76so-pooler.c-10.us-east-1.aws.neon.tech")
DB_USER = os.getenv("DB_USER", "neondb_owner")
DB_PASS = os.getenv("DB_PASS", "npg_1RJYwpx8SvVK")
DB_NAME = os.getenv("DB_NAME", "neondb")
DB_PORT = os.getenv("DB_PORT", "5432")

MODELS_DIR = os.path.abspath(os.path.join(os.path.dirname(__file__), "..", "models"))

def get_db_connection():
    try:
        conn = psycopg2.connect(
            host=DB_HOST,
            user=DB_USER,
            password=DB_PASS,
            dbname=DB_NAME,
            port=DB_PORT,
            sslmode="require"
        )
        return conn
    except Exception as e:
        return None

def store_prediction(db_conn, target_type, target_entity_id, pred_val, conf_lower, conf_upper, model_ver="v1.0"):
    if not db_conn:
        return None
    cursor = db_conn.cursor()
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    cursor.execute("""
        INSERT INTO predictions 
          (target_type, target_entity_id, predicted_value, confidence_lower, confidence_upper, prediction_time, model_version)
        VALUES 
          (%s, %s, %s, %s, %s, %s, %s)
        RETURNING prediction_id
    """, (target_type, target_entity_id, float(pred_val), float(conf_lower), float(conf_upper), now, model_ver))
    pred_id = cursor.fetchone()[0]
    db_conn.commit()
    cursor.close()
    return pred_id

def predict_patient_arrivals(data, db_conn=None):
    model_path = os.path.join(MODELS_DIR, "patient_arrival_model.joblib")
    if not os.path.exists(model_path):
        raise FileNotFoundError("Patient arrival model not found. Run training first.")
    
    saved = joblib.load(model_path)
    model = saved["model"]
    
    # Extract features with defaults
    hour = int(data.get("arrival_hour", 9))
    dow = int(data.get("day_of_week", 1))
    dept_id = int(data.get("department_id", 1))
    appt_cnt = int(data.get("appointment_count", 5))
    hist_avg = float(data.get("historical_avg_arrivals", 4.5))
    
    X = np.array([[hour, dow, dept_id, appt_cnt, hist_avg]])
    raw_pred = model.predict(X)[0]
    pred_val = max(0.0, round(float(raw_pred), 2))
    
    conf_lower = max(0.0, round(pred_val * 0.85, 2))
    conf_upper = round(pred_val * 1.15, 2)
    
    pred_id = store_prediction(db_conn, "PATIENT_ARRIVALS", f"DEPT-{dept_id}", pred_val, conf_lower, conf_upper)
    
    return {
        "target_type": "PATIENT_ARRIVALS",
        "prediction_id": pred_id,
        "predicted_value": pred_val,
        "confidence_lower": conf_lower,
        "confidence_upper": conf_upper,
        "unit": "patients"
    }

def predict_waiting_time(data, db_conn=None):
    model_path = os.path.join(MODELS_DIR, "waiting_time_model.joblib")
    if not os.path.exists(model_path):
        raise FileNotFoundError("Waiting time model not found. Run training first.")
    
    saved = joblib.load(model_path)
    model = saved["model"]
    
    hour = int(data.get("arrival_hour", 10))
    dow = int(data.get("day_of_week", 1))
    queue_pos = int(data.get("queue_position", 3))
    doc_active = int(data.get("doctor_active_patients", 3))
    est_wait = float(data.get("estimated_wait_minutes", 15.0))
    is_bottleneck = 1 if data.get("is_bottleneck", False) else 0
    
    X = np.array([[hour, dow, queue_pos, doc_active, est_wait, is_bottleneck]])
    raw_pred = model.predict(X)[0]
    pred_val = max(0.0, round(float(raw_pred), 2))
    
    conf_lower = max(0.0, round(pred_val * 0.85, 2))
    conf_upper = round(pred_val * 1.15, 2)
    
    pred_id = store_prediction(db_conn, "OPD_WAITING_TIME", f"POS-{queue_pos}", pred_val, conf_lower, conf_upper)
    
    return {
        "target_type": "OPD_WAITING_TIME",
        "prediction_id": pred_id,
        "predicted_value": pred_val,
        "confidence_lower": conf_lower,
        "confidence_upper": conf_upper,
        "unit": "minutes"
    }

def predict_bed_occupancy(data, db_conn=None):
    model_path = os.path.join(MODELS_DIR, "bed_occupancy_model.joblib")
    if not os.path.exists(model_path):
        raise FileNotFoundError("Bed occupancy model not found. Run training first.")
    
    saved = joblib.load(model_path)
    model = saved["model"]
    
    total_beds = int(data.get("total_beds", 10))
    occupied_beds = int(data.get("occupied_beds", 6))
    available_beds = int(data.get("available_beds", 4))
    admissions_cnt = int(data.get("admissions_count", 2))
    discharges_cnt = int(data.get("discharges_count", 1))
    avg_stay = float(data.get("avg_stay_duration_days", 2.5))
    
    X = np.array([[total_beds, occupied_beds, available_beds, admissions_cnt, discharges_cnt, avg_stay]])
    raw_pred = model.predict(X)[0]
    # Bound occupancy strictly between 0.0% and 100.0%
    pred_val = min(100.0, max(0.0, round(float(raw_pred), 2)))
    
    conf_lower = max(0.0, round(pred_val * 0.90, 2))
    conf_upper = min(100.0, round(pred_val * 1.10, 2))
    
    pred_id = store_prediction(db_conn, "BED_OCCUPANCY", f"WARD-{data.get('bed_type', 'GENERAL')}", pred_val, conf_lower, conf_upper)
    
    return {
        "target_type": "BED_OCCUPANCY",
        "prediction_id": pred_id,
        "predicted_value": pred_val,
        "confidence_lower": conf_lower,
        "confidence_upper": conf_upper,
        "unit": "percent"
    }

def predict_doctor_workload(data, db_conn=None):
    model_path = os.path.join(MODELS_DIR, "doctor_workload_model.joblib")
    if not os.path.exists(model_path):
        raise FileNotFoundError("Doctor workload model not found. Run training first.")
    
    saved = joblib.load(model_path)
    reg_model = saved["reg_model"]
    cls_model = saved["cls_model"]
    
    opd = int(data.get("opd_patient_count", 10))
    ipd = int(data.get("ipd_patient_count", 3))
    tot = int(data.get("total_patient_count", 13))
    avg_consult = float(data.get("avg_consult_duration_minutes", 15.0))
    q_len = int(data.get("queue_length", 2))
    
    X = np.array([[opd, ipd, tot, avg_consult, q_len]])
    raw_score = reg_model.predict(X)[0]
    score_val = max(0.0, round(float(raw_score), 2))
    
    risk_level = cls_model.predict(X)[0]
    
    conf_lower = max(0.0, round(score_val * 0.85, 2))
    conf_upper = round(score_val * 1.15, 2)
    
    doc_id = data.get("doctor_id", 1)
    pred_id = store_prediction(db_conn, "DOCTOR_WORKLOAD", f"DOC-{doc_id}", score_val, conf_lower, conf_upper)
    
    return {
        "target_type": "DOCTOR_WORKLOAD",
        "prediction_id": pred_id,
        "predicted_value": score_val,
        "burnout_risk_level": risk_level,
        "confidence_lower": conf_lower,
        "confidence_upper": conf_upper,
        "unit": "workload_score"
    }

def main():
    if len(sys.argv) < 2:
        print(json.dumps({"status": "ERROR", "message": "Missing target_type argument. Usage: predict.py <TARGET_TYPE> [JSON_DATA]"}))
        sys.exit(1)
        
    target_type = sys.argv[1].upper()
    data = {}
    if len(sys.argv) >= 3:
        try:
            data = json.loads(sys.argv[2])
        except Exception:
            data = {}

    conn = get_db_connection()
    try:
        if target_type in ["PATIENT_ARRIVALS", "ARRIVALS"]:
            res = predict_patient_arrivals(data, conn)
        elif target_type in ["OPD_WAITING_TIME", "WAITING_TIME"]:
            res = predict_waiting_time(data, conn)
        elif target_type in ["BED_OCCUPANCY", "BEDS"]:
            res = predict_bed_occupancy(data, conn)
        elif target_type in ["DOCTOR_WORKLOAD", "WORKLOAD"]:
            res = predict_doctor_workload(data, conn)
        elif target_type == "ALL":
            res = {
                "patient_arrivals": predict_patient_arrivals(data.get("patient_arrivals", {}), conn),
                "waiting_time": predict_waiting_time(data.get("waiting_time", {}), conn),
                "bed_occupancy": predict_bed_occupancy(data.get("bed_occupancy", {}), conn),
                "doctor_workload": predict_doctor_workload(data.get("doctor_workload", {}), conn)
            }
        else:
            print(json.dumps({"status": "ERROR", "message": f"Unknown target_type '{target_type}'"}))
            sys.exit(1)
            
        output = {
            "status": "SUCCESS",
            "timestamp": datetime.now().isoformat(),
            "prediction": res
        }
        print(json.dumps(output, indent=2))
    except Exception as e:
        print(json.dumps({"status": "ERROR", "message": str(e)}))
        sys.exit(1)
    finally:
        if conn:
            conn.close()

if __name__ == "__main__":
    main()
