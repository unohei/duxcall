# backend/app/db.py
import os
from dotenv import load_dotenv

from sqlalchemy import create_engine
from sqlalchemy.orm import sessionmaker, DeclarativeBase

# .env を読み込む（backend/.env）
# 例: DB_HOST=127.0.0.1 など
load_dotenv()


class Base(DeclarativeBase):
    """SQLAlchemy 2.0 style Base"""
    pass


def _get_database_url() -> str:
    """
    XAMPP(MySQL) につなぐための SQLAlchemy URL を組み立てる。
    mysql+pymysql://<user>:<pass>@<host>:<port>/<db>?charset=utf8mb4
    """
    host = os.getenv("DB_HOST", "127.0.0.1")
    port = os.getenv("DB_PORT", "3306")
    name = os.getenv("DB_NAME", "medical_mvp")
    user = os.getenv("DB_USER", "root")
    password = os.getenv("DB_PASSWORD", "")

    # パスワードに記号が入る可能性があるので URL エンコード
    from urllib.parse import quote_plus
    password_enc = quote_plus(password)

    return f"mysql+pymysql://{user}:{password_enc}@{host}:{port}/{name}?charset=utf8mb4"


DATABASE_URL = _get_database_url()

# pool_pre_ping=True は「MySQL接続が切れてた」を自動検知して復帰しやすくするおまじない
engine = create_engine(
    DATABASE_URL,
    echo=False,           # SQLをログに出したいなら True
    pool_pre_ping=True,
    future=True
)

SessionLocal = sessionmaker(
    bind=engine,
    autoflush=False,
    autocommit=False,
)


def get_db():
    """
    FastAPI Depends 用。
    使い方例:
      @app.get(...)
      def endpoint(db: Session = Depends(get_db)):
          ...
    """
    db = SessionLocal()
    try:
        yield db
    finally:
        db.close()