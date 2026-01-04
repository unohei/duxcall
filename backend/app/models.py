# backend/app/models.py
from __future__ import annotations

from datetime import datetime, date, time
from typing import List, Optional

from sqlalchemy import (
    String,
    Integer,
    Boolean,
    DateTime,
    Date,
    Time,
    Text,
    Enum,
    ForeignKey,
    UniqueConstraint,
)
from sqlalchemy.orm import Mapped, mapped_column, relationship

from .db import Base


class Hospital(Base):
    __tablename__ = "hospitals"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    hospital_code: Mapped[str] = mapped_column(String(64), nullable=False, unique=True, index=True)
    name: Mapped[str] = mapped_column(String(255), nullable=False)
    timezone: Mapped[str] = mapped_column(String(64), nullable=False, default="Asia/Tokyo")
    is_active: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)

    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, default=datetime.utcnow, onupdate=datetime.utcnow)

    routes: Mapped[List["Route"]] = relationship(
        back_populates="hospital",
        cascade="all, delete-orphan",
        order_by="Route.sort_order",
    )

    news_items: Mapped[List["News"]] = relationship(
        back_populates="hospital",
        cascade="all, delete-orphan",
        order_by="News.updated_at.desc()",
    )


class Route(Base):
    __tablename__ = "routes"
    __table_args__ = (
        UniqueConstraint("hospital_id", "key", name="uq_routes_hospital_key"),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    hospital_id: Mapped[int] = mapped_column(ForeignKey("hospitals.id"), nullable=False, index=True)

    key: Mapped[str] = mapped_column(String(50), nullable=False)
    label: Mapped[str] = mapped_column(String(50), nullable=False)
    phone: Mapped[str] = mapped_column(String(20), nullable=False)

    is_enabled: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)
    sort_order: Mapped[int] = mapped_column(Integer, nullable=False, default=10)

    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, default=datetime.utcnow)
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, default=datetime.utcnow, onupdate=datetime.utcnow)

    hospital: Mapped["Hospital"] = relationship(back_populates="routes")

    weekly_hours: Mapped[List["RouteWeeklyHours"]] = relationship(
        back_populates="route",
        cascade="all, delete-orphan",
        order_by="RouteWeeklyHours.dow",
    )

    exceptions: Mapped[List["RouteException"]] = relationship(
        back_populates="route",
        cascade="all, delete-orphan",
        order_by="RouteException.start_date.desc(), RouteException.created_at.desc()",
    )


class RouteWeeklyHours(Base):
    __tablename__ = "route_weekly_hours"
    __table_args__ = (
        UniqueConstraint("route_id", "dow", name="uq_weekly_route_dow"),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    route_id: Mapped[int] = mapped_column(ForeignKey("routes.id"), nullable=False, index=True)

    dow: Mapped[int] = mapped_column(Integer, nullable=False)  # 0=Mon ... 6=Sun
    open_time: Mapped[Optional[time]] = mapped_column(Time, nullable=True)
    close_time: Mapped[Optional[time]] = mapped_column(Time, nullable=True)
    is_closed: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False)

    route: Mapped["Route"] = relationship(back_populates="weekly_hours")


class RouteException(Base):
    __tablename__ = "route_exceptions"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    route_id: Mapped[int] = mapped_column(ForeignKey("routes.id"), nullable=False, index=True)

    start_date: Mapped[date] = mapped_column(Date, nullable=False)
    end_date: Mapped[date] = mapped_column(Date, nullable=False)
    title: Mapped[Optional[str]] = mapped_column(String(255), nullable=True)
    created_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, default=datetime.utcnow)

    route: Mapped["Route"] = relationship(back_populates="exceptions")
    hours: Mapped[List["RouteExceptionHours"]] = relationship(
        back_populates="exception",
        cascade="all, delete-orphan",
        order_by="RouteExceptionHours.dow",
    )


class RouteExceptionHours(Base):
    __tablename__ = "route_exception_hours"
    __table_args__ = (
        UniqueConstraint("exception_id", "dow", name="uq_ex_hours_exception_dow"),
    )

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    exception_id: Mapped[int] = mapped_column(ForeignKey("route_exceptions.id"), nullable=False, index=True)

    dow: Mapped[int] = mapped_column(Integer, nullable=False)
    open_time: Mapped[Optional[time]] = mapped_column(Time, nullable=True)
    close_time: Mapped[Optional[time]] = mapped_column(Time, nullable=True)
    is_closed: Mapped[bool] = mapped_column(Boolean, nullable=False, default=False)

    exception: Mapped["RouteException"] = relationship(back_populates="hours")


class News(Base):
    __tablename__ = "news"

    id: Mapped[int] = mapped_column(Integer, primary_key=True, autoincrement=True)
    hospital_id: Mapped[int] = mapped_column(ForeignKey("hospitals.id"), nullable=False, index=True)

    title: Mapped[str] = mapped_column(String(255), nullable=False)
    body: Mapped[Optional[str]] = mapped_column(Text, nullable=True)

    priority: Mapped[str] = mapped_column(
        Enum("high", "normal", name="news_priority"),
        nullable=False,
        default="normal",
    )

    is_published: Mapped[bool] = mapped_column(Boolean, nullable=False, default=True)
    updated_at: Mapped[datetime] = mapped_column(DateTime, nullable=False, default=datetime.utcnow, onupdate=datetime.utcnow)

    hospital: Mapped["Hospital"] = relationship(back_populates="news_items")