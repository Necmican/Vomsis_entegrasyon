FROM python:3.11-slim

WORKDIR /app

# Matematiksel işlemler (statsmodels) için gerekli C derleyicilerini kurar
RUN apt-get update && apt-get install -y gcc && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# Sunucuyu 8000 portundan ayağa kaldır
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "8000"]