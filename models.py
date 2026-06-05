import datetime
import enum
from sqlalchemy import Column, Integer, String, Text, ForeignKey, Enum as SQLAlchemyEnum, TIMESTAMP, Boolean
from sqlalchemy.orm import relationship
from database import Base

# تعريف الصلاحيات كـ Enum
class UserRole(enum.Enum):
    user = "user"
    admin = "admin"

class User(Base):
    __tablename__ = "users"
    id = Column(Integer, primary_key=True, index=True)
    username = Column(String(50), nullable=False)
    passwor = Column(String(255), nullable=False)
    
    # استخدام Enum للصلاحيات بدلاً من Boolean
    role = Column(SQLAlchemyEnum(UserRole), default=UserRole.user) 

    terms = relationship("Term", back_populates="owner")
    sections = relationship("Section", back_populates="owner")
    upgrade_requests = relationship("UpgradeRequest", back_populates="owner")

class Term(Base):
    __tablename__ = "terms"
    id = Column(Integer, primary_key=True, index=True)
    term = Column(String(50), nullable=False)
    trans = Column(Text)
    defe = Column(Text)
    picture = Column(String(255), default="pic/default.png", nullable=True) 
    status = Column(SQLAlchemyEnum('pending', 'approved', 'rejected', name="status_enum"), default='pending')
    user_id = Column(Integer, ForeignKey("users.id", ondelete="CASCADE"))

    owner = relationship("User", back_populates="terms")

class Section(Base):
    __tablename__ = "sections"
    s_id = Column(Integer, primary_key=True, index=True)
    section_name = Column(String(100), nullable=False)
    user_id = Column(Integer, ForeignKey("users.id", ondelete="CASCADE"))

    owner = relationship("User", back_populates="sections")

class FailedAttempt(Base):
    __tablename__ = "failed_attempts"
    id = Column(Integer, primary_key=True, index=True)
    ip_address = Column(String(45), nullable=False)
    attempt_time = Column(TIMESTAMP, default=datetime.datetime.utcnow)

class UpgradeRequest(Base):
    __tablename__ = "upgrade_requests"
    id = Column(Integer, primary_key=True, index=True)
    user_id = Column(Integer, ForeignKey("users.id", ondelete="CASCADE"))

    owner = relationship("User", back_populates="upgrade_requests")
